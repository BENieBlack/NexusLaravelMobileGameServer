<?php

namespace NexusBilling\Tests\Unit\ApiClients;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
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

    /** @var list<string> テスト中に作った一時ファイル */
    private array $temporaryFiles = [];

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

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
        $this->temporaryFiles = [];

        parent::tearDown();
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
    public function 返金履歴はrevisionを渡すと続きから取得する(): void
    {
        Http::fake(['*' => Http::response(['signedTransactions' => [], 'hasMore' => false])]);

        $this->client->fetchRefundHistory('1000000000000001', 'revision-token/2');

        // revisionはURLに載るのでエスケープが要る
        Http::assertSent(fn ($request) => $request->url() === 'https://api.storekit-sandbox.itunes.apple.com'
            .'/inApps/v2/refund/lookup/1000000000000001?revision=revision-token%2F2');
    }

    #[Test]
    public function 空のrevisionは1ページ目として扱う(): void
    {
        Http::fake(['*' => Http::response(['signedTransactions' => []])]);

        $this->client->fetchRefundHistory('1000000000000001', '');

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'revision'));
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
    public function sandboxのレシートを本番に送ると自動でsandboxへ送り直す(): void
    {
        // 審査中のアプリではsandboxのレシートが本番に届く。
        // Appleはこれを21007で返すので、sandboxへ送り直す必要がある
        config(['services.app_store.environment' => 'production']);

        Http::fake([
            'buy.itunes.apple.com/*' => Http::response(['status' => 21007]),
            'sandbox.itunes.apple.com/*' => Http::response(['status' => 0, 'receipt' => ['bundle_id' => 'com.example.nexus']]),
        ]);

        $result = $this->client->verifyReceipt(['receipt-data' => 'dummy']);

        $this->assertSame(0, $result['status']);
        $this->assertSame('com.example.nexus', $result['receipt']['bundle_id']);
        Http::assertSentCount(2);
    }

    #[Test]
    public function sandboxで21007が返っても送り直さない(): void
    {
        // 送り直すと無限に往復するため、sandboxからの21007はそのまま返す
        Http::fake(['*' => Http::response(['status' => 21007])]);

        $result = $this->client->verifyReceipt(['receipt-data' => 'dummy']);

        $this->assertSame(21007, $result['status']);
        Http::assertSentCount(1);
    }

    #[Test]
    public function レシート検証がhttpエラーなら例外になる(): void
    {
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        // Appleが応答している以上「通信できなかった」ではない。
        // Server API側と同じく、ステータスの見えるメッセージのまま上げる
        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('App Store API returned error: HTTP 503');

        $this->client->verifyReceipt(['receipt-data' => 'dummy']);
    }

    #[Test]
    public function レシート検証が通信できなければ例外になる(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('Failed to communicate with App Store');

        $this->client->verifyReceipt(['receipt-data' => 'dummy']);
    }

    #[Test]
    public function server_apiがhttpエラーなら例外になる(): void
    {
        Http::fake(['*' => Http::response(['errorCode' => 4040010], 404)]);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('App Store Server API returned error: HTTP 404');

        $this->client->fetchSubscriptionStatuses('1000000000000001');
    }

    #[Test]
    public function server_apiが通信できなければ例外になる(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('Failed to communicate with App Store Server API');

        $this->client->fetchSubscriptionStatuses('1000000000000001');
    }

    #[Test]
    public function 認証jwtにappleが要求するクレームが入る(): void
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
    public function 認証jwtはキャッシュされ作り直されない(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->client->fetchSubscriptionStatuses('1000000000000001');
        $this->client->fetchSubscriptionStatuses('1000000000000002');

        // ES256の署名は毎回変わるため、同じトークンならキャッシュから来ている
        $tokens = [];
        Http::assertSent(function ($request) use (&$tokens) {
            $tokens[] = $request->header('Authorization')[0];

            return true;
        });

        $this->assertCount(2, $tokens);
        $this->assertSame($tokens[0], $tokens[1]);
    }

    #[Test]
    public function 有効期間はappleの上限3600秒に丸められる(): void
    {
        config(['services.app_store.jwt.ttl' => 7200]);

        Http::fake(['*' => Http::response(['data' => []])]);

        $this->client->fetchSubscriptionStatuses('1000000000000001');

        Http::assertSent(function ($request) {
            $token = substr($request->header('Authorization')[0], strlen('Bearer '));
            $payload = json_decode(
                base64_decode(strtr(explode('.', $token)[1], '-_', '+/')),
                true
            );

            return $payload['exp'] - $payload['iat'] === 3600;
        });
    }

    #[Test]
    public function 秘密鍵はp8ファイルからも読める(): void
    {
        config(['services.app_store.jwt.private_key' => $this->writeTemporaryFile($this->privateKey, '.p8')]);

        Http::fake(['*' => Http::response(['data' => []])]);

        $this->client->fetchSubscriptionStatuses('1000000000000001');

        Http::assertSent(fn ($request) => str_starts_with($request->header('Authorization')[0], 'Bearer '));
    }

    #[Test]
    public function 秘密鍵ファイルが読めなければ例外になる(): void
    {
        config(['services.app_store.jwt.private_key' => '/no/such/AuthKey_ABCD123456.p8']);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('App Store private key file is not readable');

        $this->client->fetchSubscriptionStatuses('1000000000000001');
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
    public function jwsの形をしていなければ受け付けない(): void
    {
        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('App Store signed payload is malformed');

        $this->client->decodeSignedPayload('not-a-jws');
    }

    #[Test]
    public function 証明書として読めないx5cは受け付けない(): void
    {
        $signed = JWT::encode(
            ['transactionId' => '1'],
            $this->privateKey,
            'ES256',
            null,
            ['x5c' => [base64_encode('this is not a certificate')]]
        );

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('App Store certificate could not be parsed');

        $this->client->decodeSignedPayload($signed);
    }

    #[Test]
    public function ルート証明書を設定すると証明書チェーンも検証する(): void
    {
        $ca = $this->generateCertificateAuthority();
        [$leafKey, $leafCertificate] = $this->generateEcKeyPair($ca);

        config(['services.app_store.jwt.root_certificate' => $ca['pem']]);

        $signed = $this->signPayload(
            ['transactionId' => '1000000000000001'],
            privateKey: $leafKey,
            certificate: $leafCertificate
        );

        $payload = $this->client->decodeSignedPayload($signed);

        $this->assertSame('1000000000000001', $payload['transactionId']);
    }

    #[Test]
    public function ルート証明書に繋がらないチェーンは受け付けない(): void
    {
        // setUp のリーフは自己署名。別のCAをルートに設定すると繋がらない
        config(['services.app_store.jwt.root_certificate' => $this->generateCertificateAuthority()['pem']]);

        $signed = $this->signPayload(['transactionId' => '1']);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('App Store certificate chain verification failed');

        $this->client->decodeSignedPayload($signed);
    }

    #[Test]
    public function ルート証明書はファイルからも読める(): void
    {
        $ca = $this->generateCertificateAuthority();
        [$leafKey, $leafCertificate] = $this->generateEcKeyPair($ca);

        config([
            'services.app_store.jwt.root_certificate' => $this->writeTemporaryFile($ca['pem'], '.pem'),
        ]);

        $payload = $this->client->decodeSignedPayload($this->signPayload(
            ['transactionId' => '1000000000000001'],
            privateKey: $leafKey,
            certificate: $leafCertificate
        ));

        $this->assertSame('1000000000000001', $payload['transactionId']);
    }

    #[Test]
    public function ルート証明書ファイルが読めなければ例外になる(): void
    {
        config(['services.app_store.jwt.root_certificate' => '/no/such/AppleRootCA-G3.cer']);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('App Store root certificate is not readable');

        $this->client->decodeSignedPayload($this->signPayload(['transactionId' => '1']));
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
    private function signPayload(array $payload, ?string $privateKey = null, ?string $certificate = null): string
    {
        $der = $this->certificateToBase64Der($certificate ?? $this->certificate);

        return JWT::encode(
            $payload,
            $privateKey ?? $this->privateKey,
            'ES256',
            null,
            ['x5c' => [$der]]
        );
    }

    /**
     * EC(P-256)の鍵と証明書を作る
     *
     * $issuer を渡すとそのCAで署名し、省略すると自己署名になる。
     *
     * @param  array{key: \OpenSSLAsymmetricKey, pem: string}|null  $issuer
     * @return array{0: string, 1: string} [秘密鍵PEM, 証明書PEM]
     */
    private function generateEcKeyPair(?array $issuer = null): array
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        openssl_pkey_export($key, $privateKey);

        $csr = openssl_csr_new(['commonName' => 'Test Apple Leaf'], $key, ['digest_alg' => 'sha256']);
        $certificate = openssl_csr_sign(
            $csr,
            $issuer['pem'] ?? null,
            $issuer['key'] ?? $key,
            365,
            ['digest_alg' => 'sha256']
        );
        openssl_x509_export($certificate, $certificatePem);

        return [$privateKey, $certificatePem];
    }

    /**
     * リーフに署名するCAを作る（Apple Root CA - G3 に相当）
     *
     * @return array{key: \OpenSSLAsymmetricKey, pem: string}
     */
    private function generateCertificateAuthority(): array
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        $csr = openssl_csr_new(['commonName' => 'Test Apple Root CA'], $key, ['digest_alg' => 'sha256']);
        $certificate = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($certificate, $certificatePem);

        return ['key' => $key, 'pem' => $certificatePem];
    }

    /**
     * PEM証明書をx5cに載せる形（ヘッダー無しのbase64 DER）にする
     */
    private function certificateToBase64Der(string $pem): string
    {
        $body = preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s+/', '', $pem);

        return (string) $body;
    }

    /**
     * 内容を一時ファイルに書き出してパスを返す（tearDownで消す）
     */
    private function writeTemporaryFile(string $contents, string $suffix): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nexus-billing').$suffix;
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
