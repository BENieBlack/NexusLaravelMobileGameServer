<?php

namespace Tests\Feature\InAppPurchase;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * POST /api/in_app_purchase/buy のテスト
 *
 * Controller / Request / UseCase を実際のHTTP経路で通す。
 * 外部の課金APIだけフェイクする。
 */
class BuyEndpointTest extends TestCase
{
    private const DEPLOY_KEY = 202601010;

    private const PRICE_MICROS = 490_000_000;

    private const PAID_DIAMOND_AMOUNT = 500;

    /** 実行ごとに一意にする。trx_in_app_purchase の transaction_id が一意制約のため */
    private string $orderId;

    private string $accessToken;

    private int $sysPlayerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderId = 'GPA.ENDPOINT-'.Str::random(12);

        $this->cleanUpMaster();
        $this->cleanUpPurchases();
        $this->refreshMstCache();
        $this->fakeGooglePlay();

        config([
            'services.google_play.package_name' => 'com.example.nexus',
            'services.google_play.service_account' => json_encode([
                'client_email' => 'nexus@example.iam.gserviceaccount.com',
                'private_key' => $this->generatePrivateKey(),
            ]),
        ]);

        [$this->accessToken, $this->sysPlayerId] = $this->signUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUpMaster();
        $this->cleanUpPurchases();
        parent::tearDown();
    }

    #[Test]
    public function ダイヤモンド商品を購入できる(): void
    {
        $mstInAppPurchaseId = $this->createDiamondProduct();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->accessToken])
            ->postJson('/api/in_app_purchase/buy', [
                'mst_in_app_purchase_id' => $mstInAppPurchaseId,
                'platform' => 'Google',
                'billing_platform' => 'GooglePlay',
                'receipt' => 'purchase-token-endpoint',
                'transaction_id' => 'GPA.ENDPOINT-0001',
                'product_id' => 'diamond_500',
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'paid_diamond_amount',
            'total_paid_diamond_amount',
            'total_free_diamond_amount',
        ]);
        $this->assertSame(self::PAID_DIAMOND_AMOUNT, $response->json('paid_diamond_amount'));

        // 実際に付与されている
        $diamond = DB::connection($this->playerConnection($this->sysPlayerId))->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)
            ->first();
        $this->assertNotNull($diamond);
        $this->assertSame(self::PAID_DIAMOND_AMOUNT, $diamond->paid_amount);
    }

    #[Test]
    public function 存在しない商品_i_dはエラーになる(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->accessToken])
            ->postJson('/api/in_app_purchase/buy', [
                'mst_in_app_purchase_id' => 999999,
                'platform' => 'Google',
                'billing_platform' => 'GooglePlay',
                'receipt' => 'purchase-token-endpoint',
                'transaction_id' => 'GPA.ENDPOINT-0002',
                'product_id' => 'diamond_500',
            ]);

        $response->assertJsonPath('error_code', 16001); // PRODUCT_NOT_FOUND
    }

    #[Test]
    public function app_storeでレシートのプロダクト_i_dが違うとエラーになる(): void
    {
        $mstInAppPurchaseId = $this->createDiamondProduct('AppStore');

        // レシートは diamond_500 のものだが、リクエストは diamond_999 を主張する
        Http::fake([
            '*itunes.apple.com/verifyReceipt' => Http::response([
                'status' => 0,
                'receipt' => [
                    'in_app' => [[
                        'transaction_id' => $this->orderId,
                        'original_transaction_id' => $this->orderId,
                        'product_id' => 'diamond_500',
                        'purchase_date_ms' => '1755648000000',
                        'quantity' => '1',
                    ]],
                ],
            ]),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->accessToken])
            ->postJson('/api/in_app_purchase/buy', [
                'mst_in_app_purchase_id' => $mstInAppPurchaseId,
                'platform' => 'Apple',
                'billing_platform' => 'AppStore',
                'receipt' => 'base64-encoded-receipt',
                'transaction_id' => $this->orderId,
                'product_id' => 'diamond_999',
            ]);

        $response->assertJsonPath('error_code', 16005); // PRODUCT_ID_MISMATCH

        // 付与されていない
        $this->assertNull(
            DB::connection($this->playerConnection($this->sysPlayerId))->table('trx_diamond')->where('sys_player_id', $this->sysPlayerId)->first()
        );
    }

    #[Test]
    public function 認証なしでは購入できない(): void
    {
        $response = $this->postJson('/api/in_app_purchase/buy', [
            'mst_in_app_purchase_id' => 1,
            'platform' => 'Google',
            'billing_platform' => 'GooglePlay',
            'receipt' => 'purchase-token-endpoint',
            'product_id' => 'diamond_500',
        ]);

        $this->assertNotSame(200, $response->getStatusCode());
    }

    #[Test]
    public function 必須パラメータが欠けているとバリデーションエラーになる(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->accessToken])
            ->postJson('/api/in_app_purchase/buy', [
                'mst_in_app_purchase_id' => 1,
                // platform / billing_platform / receipt / product_id を送らない
            ]);

        $response->assertStatus(422);
    }

    /**
     * サインアップしてアクセストークンとプレイヤーIDを得る
     *
     * @return array{0: string, 1: int}
     */
    private function signUp(): array
    {
        $response = $this->postJson('/api/auth/sign_up', [
            'device_id' => 'test-device-'.Str::random(20),
            'device_info' => [
                'os' => 'Android',
                'os_version' => '14',
                'model' => 'Pixel 8',
                'app_version' => '1.0.0',
            ],
        ]);

        $response->assertOk();

        $uuid = $response->json('sys_player.uuid');
        $player = DB::connection('sys')->table('sys_player')->where('uuid', $uuid)->first();

        return [$response->json('token.access_token'), (int) $player->id];
    }

    private function fakeGooglePlay(): void
    {
        $orderId = $this->orderId;

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'androidpublisher.googleapis.com/*' => Http::response([
                'purchaseState' => 0,
                'consumptionState' => 0,
                'orderId' => $orderId,
                'purchaseTimeMillis' => '1755648000000',
                'quantity' => 1,
                'priceAmountMicros' => (string) self::PRICE_MICROS,
                'priceCurrencyCode' => 'JPY',
            ]),
        ]);
    }

    private function createDiamondProduct(string $billingPlatform = 'GooglePlay'): int
    {
        $platformProductId = DB::connection('mst')->table('mst_billing_platform_product')->insertGetId([
            'deploy_key' => self::DEPLOY_KEY,
            'platform_product_id' => 'diamond_500',
            'billing_platform' => $billingPlatform,
            'product_type' => 'Consumable',
            'price_amount_micros' => self::PRICE_MICROS,
            'price_currency_code' => 'JPY',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = (int) DB::connection('mst')->table('mst_in_app_purchase')->insertGetId([
            'deploy_key' => self::DEPLOY_KEY,
            'type' => 'Diamond',
            'paid_diamond_amount' => self::PAID_DIAMOND_AMOUNT,
            'vip_point' => 0,
            'purchase_limit_reset' => 'None',
            'app_store_product_id' => $billingPlatform === 'AppStore' ? $platformProductId : null,
            'google_play_product_id' => $billingPlatform === 'GooglePlay' ? $platformProductId : null,
            'sort_desc' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // マスタRepositoryは全件をRedisにキャッシュするため、投入後に破棄する
        $this->refreshMstCache();

        return $id;
    }

    private function cleanUpMaster(): void
    {
        DB::connection('mst')->table('mst_in_app_purchase')->delete();
        DB::connection('mst')->table('mst_billing_platform_product')->delete();
    }

    /**
     * このテストが作った購入履歴を消す
     *
     * 過去の実行分が残っていると transaction_id の一意制約に当たる
     */
    private function cleanUpPurchases(): void
    {
        // setUpのプレイヤー作成前にも呼ぶため、全シャードをまとめて掃除する
        foreach (['trx1', 'trx2'] as $connection) {
            DB::connection($connection)->table('trx_in_app_purchase')
                ->where('transaction_id', 'like', 'GPA.ENDPOINT%')
                ->delete();
        }
    }

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
