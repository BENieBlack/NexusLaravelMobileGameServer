<?php

namespace NexusBilling\Tests\Unit\ApiClients;

use Illuminate\Http\Client\ConnectionException;
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

    /** @var list<string> テスト中に作った一時ファイル */
    private array $temporaryFiles = [];

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

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
        $this->temporaryFiles = [];

        parent::tearDown();
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
    public function apiがエラーを返すと例外になる(): void
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

    #[Test]
    public function サブスクリプションの購入情報を取得できる(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'androidpublisher.googleapis.com/*' => Http::response([
                'expiryTimeMillis' => '1755648000000',
                'autoRenewing' => true,
            ]),
        ]);

        $result = $this->client->fetchSubscription(self::PACKAGE_NAME, 'monthly_pass', 'purchase-token');

        $this->assertTrue($result['autoRenewing']);

        Http::assertSent(fn ($request) => $request->url() === 'https://androidpublisher.googleapis.com/androidpublisher/v3'
            .'/applications/com.example.nexus/purchases/subscriptions/monthly_pass/tokens/purchase-token');
    }

    #[Test]
    public function 購入トークンはurlエスケープされる(): void
    {
        // 購入トークンには / や + が入る。素で繋ぐとパスが壊れる
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'androidpublisher.googleapis.com/*' => Http::response(['purchaseState' => 0]),
        ]);

        $this->client->verifyPurchase(self::PACKAGE_NAME, 'diamond_100', 'abc/def+ghi');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/tokens/abc%2Fdef%2Bghi'));
    }

    #[Test]
    public function 通信できなければ例外になる(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'androidpublisher.googleapis.com/*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
        ]);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('Failed to communicate with Google Play');

        $this->client->verifyPurchase(self::PACKAGE_NAME, 'diamond_100', 'purchase-token');
    }

    #[Test]
    public function アクセストークンが返らなければ例外になる(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['token_type' => 'Bearer']),
        ]);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('Google Play access token was not returned');

        $this->client->verifyPurchase(self::PACKAGE_NAME, 'diamond_100', 'purchase-token');
    }

    #[Test]
    public function サービスアカウントはjsonファイルからも読める(): void
    {
        $json = (string) config('services.google_play.service_account');
        config(['services.google_play.service_account' => $this->writeTemporaryFile($json)]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'androidpublisher.googleapis.com/*' => Http::response(['purchaseState' => 0]),
        ]);

        $result = $this->client->verifyPurchase(self::PACKAGE_NAME, 'diamond_100', 'purchase-token');

        $this->assertSame(0, $result['purchaseState']);
    }

    #[Test]
    public function サービスアカウントのファイルが読めなければ例外になる(): void
    {
        config(['services.google_play.service_account' => '/no/such/service-account.json']);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('service account file is not readable');

        $this->client->verifyPurchase(self::PACKAGE_NAME, 'diamond_100', 'purchase-token');
    }

    #[Test]
    public function サービスアカウントjsonに鍵が欠けていれば例外になる(): void
    {
        config(['services.google_play.service_account' => json_encode(['client_email' => 'nexus@example.com'])]);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('must contain client_email and private_key');

        $this->client->verifyPurchase(self::PACKAGE_NAME, 'diamond_100', 'purchase-token');
    }

    /**
     * 内容を一時ファイルに書き出してパスを返す（tearDownで消す）
     */
    private function writeTemporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nexus-billing').'.json';
        file_put_contents($path, $contents);
        $this->temporaryFiles[] = $path;

        return $path;
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
