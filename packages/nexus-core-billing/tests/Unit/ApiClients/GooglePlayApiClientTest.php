<?php

namespace NexusBilling\Tests\Unit\ApiClients;

use Illuminate\Support\Facades\Http;
use NexusBilling\ApiClients\GooglePlayApiClient;
use NexusBilling\Exceptions\PlatformApiException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GooglePlayApiClientのテスト
 *
 * 実際のGoogle Play APIは叩かず、HTTPをフェイクして
 * 認証・リクエスト先・エラー処理を検証する。
 */
class GooglePlayApiClientTest extends TestCase
{
    private const PACKAGE_NAME = 'com.example.nexus';

    private GooglePlayApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_play.package_name' => self::PACKAGE_NAME,
            'services.google_play.service_account' => json_encode([
                'client_email' => 'nexus@example.iam.gserviceaccount.com',
                'private_key' => $this->generatePrivateKey(),
            ]),
        ]);

        // アクセストークンはキャッシュされるため、テストごとに消す
        cache()->flush();

        $this->client = new GooglePlayApiClient;
    }

    #[Test]
    public function 購入検証で商品の購入情報を取得できる(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            'androidpublisher.googleapis.com/*' => Http::response([
                'purchaseState' => 0,
                'orderId' => 'GPA.0000-1111-2222-33333',
                'purchaseTimeMillis' => '1755648000000',
            ]),
        ]);

        $result = $this->client->verifyPurchase(self::PACKAGE_NAME, 'diamond_100', 'purchase-token');

        $this->assertSame(0, $result['purchaseState']);
        $this->assertSame('GPA.0000-1111-2222-33333', $result['orderId']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://androidpublisher.googleapis.com/androidpublisher/v3'
                .'/applications/com.example.nexus/purchases/products/diamond_100/tokens/purchase-token'
                && $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    #[Test]
    public function 注文取得で返金状態を引ける(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'androidpublisher.googleapis.com/*' => Http::response(['state' => 'refunded']),
        ]);

        $result = $this->client->fetchOrder(self::PACKAGE_NAME, 'GPA.0000-1111-2222-33333');

        $this->assertSame('refunded', $result['state']);
    }

    #[Test]
    public function アクセストークンはキャッシュされ再取得されない(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'androidpublisher.googleapis.com/*' => Http::response(['purchaseState' => 0]),
        ]);

        $this->client->verifyPurchase(self::PACKAGE_NAME, 'diamond_100', 'token-1');
        $this->client->verifyPurchase(self::PACKAGE_NAME, 'diamond_100', 'token-2');

        Http::assertSentCount(3); // トークン取得1回 + 購入検証2回
    }

    #[Test]
    public function APIがエラーを返すと例外になる(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'androidpublisher.googleapis.com/*' => Http::response(['error' => 'not found'], 404),
        ]);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('HTTP 404');

        $this->client->verifyPurchase(self::PACKAGE_NAME, 'diamond_100', 'purchase-token');
    }

    #[Test]
    public function サービスアカウント未設定なら例外になる(): void
    {
        config(['services.google_play.service_account' => null]);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('service account is not configured');

        $this->client->verifyPurchase(self::PACKAGE_NAME, 'diamond_100', 'purchase-token');
    }

    #[Test]
    public function トークン取得に失敗すると例外になる(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('Failed to obtain Google Play access token');

        $this->client->verifyPurchase(self::PACKAGE_NAME, 'diamond_100', 'purchase-token');
    }

    /**
     * テスト用のRSA秘密鍵を生成する
     */
    private function generatePrivateKey(): string
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($resource, $privateKey);

        return $privateKey;
    }
}
