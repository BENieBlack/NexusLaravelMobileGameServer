<?php

namespace NexusBilling\Tests\Unit\ApiClients;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use NexusBilling\ApiClients\AppStoreApiClient;
use NexusBilling\Exceptions\PlatformApiException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AppStoreApiClientのテスト
 *
 * Apple のAPIは叩かず、HTTPをフェイクして
 * JWT認証・接続先の切り替え・JWSの署名検証を確認する。
 */
class AppStoreServerApiTest extends TestCase
{
    private AppStoreApiClient $client;

    /** テスト用のEC鍵（Appleの.p8に相当） */
    private string $privateKey;

    /** 上記の鍵で署名した自己署名証明書（Appleのx5cリーフに相当） */
    private string $certificate;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->privateKey, $this->certificate] = $this->generateEcKeyPair();

        config([
            'services.app_store.environment' => 'sandbox',
            'services.app_store.jwt.key_id' => 'ABCD123456',
            'services.app_store.jwt.issuer_id' => '57246542-96fe-1a63-e053-0824d011072a',
            'services.app_store.jwt.bundle_id' => 'com.example.nexus',
            'services.app_store.jwt.private_key' => $this->privateKey,
            'services.app_store.jwt.ttl' => 1800,
            'services.app_store.jwt.root_certificate' => null,
        ]);

        cache()->flush();

        $this->client = new AppStoreApiClient;
    }

    #[Test]
    public function サブスクリプション状態をsandboxから取得する(): void
    {
        Http::fake(['api.storekit-sandbox.itunes.apple.com/*' => Http::response(['data' => []])]);

        $this->client->fetchSubscriptionStatuses('1000000000000001');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.storekit-sandbox.itunes.apple.com'
                .'/inApps/v1/subscriptions/1000000000000001'
                && str_starts_with($request->header('Authorization')[0], 'Bearer ');
        });
    }

    #[Test]
    public function productionを指定すると本番のエンドポイントを使う(): void
    {
        config(['services.app_store.environment' => 'production']);

        Http::fake(['api.storekit.itunes.apple.com/*' => Http::response(['signedTransactions' => []])]);

        $this->client->fetchRefundHistory('1000000000000001');

        Http::assertSent(fn ($request) => str_starts_with(
            $request->url(),
            'https://api.storekit.itunes.apple.com/inApps/v2/refund/lookup/'
        ));
    }

    #[Test]
    public function レシート検証も同じ環境設定に従う(): void
    {
        config(['services.app_store.environment' => 'production']);

        Http::fake(['*' => Http::response(['status' => 0])]);

        $this->client->verifyReceipt(['receipt-data' => 'dummy']);

        Http::assertSent(fn ($request) => $request->url() === 'https://buy.itunes.apple.com/verifyReceipt');
    }

    #[Test]
    public function 認証JWTにAppleが要求するクレームが入る(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->client->fetchSubscriptionStatuses('1000000000000001');

        Http::assertSent(function ($request) {
            $token = substr($request->header('Authorization')[0], strlen('Bearer '));
            [$header, $payload] = array_map(
                fn ($segment) => json_decode(base64_decode(strtr($segment, '-_', '+/')), true),
                array_slice(explode('.', $token), 0, 2)
            );

            return $header['alg'] === 'ES256'
                && $header['kid'] === 'ABCD123456'
                && $payload['iss'] === '57246542-96fe-1a63-e053-0824d011072a'
                && $payload['aud'] === 'appstoreconnect-v1'
                && $payload['bid'] === 'com.example.nexus'
                && $payload['exp'] - $payload['iat'] === 1800;
        });
    }

    #[Test]
    public function 署名付きペイロードを検証して中身を取り出せる(): void
    {
        $signed = $this->signPayload(['transactionId' => '1000000000000001', 'productId' => 'diamond_500']);

        $payload = $this->client->decodeSignedPayload($signed);

        $this->assertSame('1000000000000001', $payload['transactionId']);
        $this->assertSame('diamond_500', $payload['productId']);
    }

    #[Test]
    public function 署名が証明書と一致しなければ例外になる(): void
    {
        // 別の鍵で署名し、証明書だけ正しいものを載せる
        [$otherKey] = $this->generateEcKeyPair();
        $signed = $this->signPayload(['transactionId' => '1'], privateKey: $otherKey);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('Failed to verify App Store signed payload');

        $this->client->decodeSignedPayload($signed);
    }

    #[Test]
    public function 証明書が無いペイロードは受け付けない(): void
    {
        $signed = JWT::encode(['transactionId' => '1'], $this->privateKey, 'ES256');

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('no certificate chain');

        $this->client->decodeSignedPayload($signed);
    }

    #[Test]
    public function 設定が無ければ例外になる(): void
    {
        config(['services.app_store.jwt.key_id' => null]);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('APP_STORE_JWT_KEY_ID');

        $this->client->fetchSubscriptionStatuses('1000000000000001');
    }

    /**
     * Appleが返すJWSと同じ形（x5cにリーフ証明書）で署名する
     *
     * @param  array<string, mixed>  $payload
     */
    private function signPayload(array $payload, ?string $privateKey = null): string
    {
        $der = $this->certificateToBase64Der($this->certificate);

        return JWT::encode(
            $payload,
            $privateKey ?? $this->privateKey,
            'ES256',
            null,
            ['x5c' => [$der]]
        );
    }

    /**
     * EC(P-256)の鍵と自己署名証明書を作る
     *
     * @return array{0: string, 1: string} [秘密鍵PEM, 証明書PEM]
     */
    private function generateEcKeyPair(): array
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        openssl_pkey_export($key, $privateKey);

        $csr = openssl_csr_new(['commonName' => 'Test Apple Leaf'], $key, ['digest_alg' => 'sha256']);
        $certificate = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($certificate, $certificatePem);

        return [$privateKey, $certificatePem];
    }

    /**
     * PEM証明書をx5cに載せる形（ヘッダー無しのbase64 DER）にする
     */
    private function certificateToBase64Der(string $pem): string
    {
        $body = preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s+/', '', $pem);

        return (string) $body;
    }
}
