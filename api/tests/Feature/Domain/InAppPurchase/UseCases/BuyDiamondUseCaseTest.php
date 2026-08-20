<?php

namespace Tests\Feature\Domain\InAppPurchase\UseCases;

use App\Domain\InAppPurchase\UseCases\BuyDiamondUseCase;
use App\Models\Mst\MstInAppPurchase;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use NexusBilling\Exceptions\InvalidReceiptException;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * ダイヤモンド購入のテスト
 *
 * UseCase → BillingFacade → 各プラットフォームのService → ApiClient まで通し、
 * 外部APIだけをフェイクして検証する。
 */
class BuyDiamondUseCaseTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const DEPLOY_KEY = 202601010;

    private const PACKAGE_NAME = 'com.example.nexus';

    private const PAID_DIAMOND_AMOUNT = 500;

    /** 商品価格 490円（マイクロ単位） */
    private const PRICE_MICROS = 490_000_000;

    /** 購入で付与されるVIPポイント */
    private const VIP_POINT = 49;

    private int $sysPlayerId = 1;

    private QueryManager $queryManager;

    /**
     * QueryManagerで明示的にトランザクションを制御するため、自動ラップを止める
     */
    public function beginDatabaseTransaction(): void
    {
        // Do nothing
    }

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->queryManager = app(QueryManager::class);

        config([
            'services.google_play.package_name' => self::PACKAGE_NAME,
            'services.google_play.service_account' => json_encode([
                'client_email' => 'nexus@example.iam.gserviceaccount.com',
                'private_key' => $this->generatePrivateKey(),
            ]),
            'services.app_store.shared_secret' => 'test-shared-secret',
        ]);

        cache()->flush();
        $this->cleanUp();
        $this->createPlayer();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        ApiSession::clearForTest();
        $this->queryManager->clear();
        parent::tearDown();
    }

    #[Test]
    public function google_playのレシートを検証してダイヤモンドを付与する(): void
    {
        $mstInAppPurchase = $this->createProduct('GooglePlay');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'androidpublisher.googleapis.com/*' => Http::response([
                'purchaseState' => 0,
                'consumptionState' => 0,
                'orderId' => 'GPA.0000-1111-2222-33333',
                'purchaseTimeMillis' => '1755648000000',
                'quantity' => 1,
                'priceAmountMicros' => (string) self::PRICE_MICROS,
                'priceCurrencyCode' => 'JPY',
            ]),
        ]);

        $response = app(BuyDiamondUseCase::class)->exec(
            $this->sysPlayerId,
            $mstInAppPurchase,
            'Google',
            'GooglePlay',
            'purchase-token-abc',
            'GPA.0000-1111-2222-33333',
            'diamond_500'
        );

        $this->queryManager->execAllQuery();

        $this->assertSame(self::PAID_DIAMOND_AMOUNT, $response->toArray()['paid_diamond_amount']);

        // 有償ダイヤが付与されている
        $diamond = DB::connection('trx1')->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('platform', 'Google')
            ->first();
        $this->assertNotNull($diamond);
        $this->assertSame(self::PAID_DIAMOND_AMOUNT, $diamond->paid_amount);

        // FIFO管理用のレコードに実際の購入価格が入っている
        $balance = DB::connection('trx1')->table('trx_diamond_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->first();
        $this->assertNotNull($balance);
        $this->assertSame(self::PAID_DIAMOND_AMOUNT, $balance->purchase_amount);
        $this->assertSame('490.00', (string) $balance->unit_price);

        // 購入履歴が残っている
        $history = DB::connection('trx1')->table('trx_in_app_purchase')
            ->where('sys_player_id', $this->sysPlayerId)
            ->first();
        $this->assertNotNull($history);

        // VIPポイントが加算され、累計課金額も積まれている
        $player = DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->first();
        $this->assertSame(self::VIP_POINT, $player->vip_point);
        $this->assertSame('490.00', (string) $player->total_paid_amount);

        // VIPポイントの変動ログが残っている
        $vipLog = DB::connection('log1')->table('log_vip_point')
            ->where('sys_player_id', $this->sysPlayerId)
            ->first();
        $this->assertNotNull($vipLog);
        $this->assertSame(self::VIP_POINT, $vipLog->point_diff);
        $this->assertSame('purchase', $vipLog->reason);

        // 課金ログが残っている（CS調査で使う）
        $purchaseLog = DB::connection('log1')->table('log_in_app_purchase')
            ->where('sys_player_id', $this->sysPlayerId)
            ->first();
        $this->assertNotNull($purchaseLog);
        $this->assertSame('google', $purchaseLog->platform);
        $this->assertSame('GooglePlay', $purchaseLog->billing_platform);
        $this->assertSame('Purchased', $purchaseLog->status);
        $this->assertSame('JPY', $purchaseLog->currency_code);
        $this->assertSame('490.00', (string) $purchaseLog->pay_amount);
        $this->assertSame('¥490', $purchaseLog->pay_string);

        // Google Playの購入検証APIを叩いている
        Http::assertSent(fn ($request) => str_contains(
            $request->url(),
            'purchases/products/diamond_500/tokens/purchase-token-abc'
        ));
    }

    #[Test]
    public function app_storeのレシートを検証してダイヤモンドを付与する(): void
    {
        $mstInAppPurchase = $this->createProduct('AppStore');

        Http::fake([
            '*itunes.apple.com/verifyReceipt' => Http::response([
                'status' => 0,
                'receipt' => [
                    'in_app' => [[
                        'transaction_id' => '1000000000000001',
                        'original_transaction_id' => '1000000000000001',
                        'product_id' => 'diamond_500',
                        'purchase_date_ms' => '1755648000000',
                        'quantity' => '1',
                    ]],
                ],
            ]),
        ]);

        app(BuyDiamondUseCase::class)->exec(
            $this->sysPlayerId,
            $mstInAppPurchase,
            'Apple',
            'AppStore',
            'base64-encoded-receipt',
            '1000000000000001',
            'diamond_500'
        );

        $this->queryManager->execAllQuery();

        $diamond = DB::connection('trx1')->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('platform', 'Apple')
            ->first();
        $this->assertNotNull($diamond);
        $this->assertSame(self::PAID_DIAMOND_AMOUNT, $diamond->paid_amount);

        // App Storeは検証結果に価格が無いため、マスターの価格が使われる
        $balance = DB::connection('trx1')->table('trx_diamond_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->first();
        $this->assertSame('490.00', (string) $balance->unit_price);
    }

    #[Test]
    public function google_playで購入済みでない場合は付与しない(): void
    {
        $mstInAppPurchase = $this->createProduct('GooglePlay');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'androidpublisher.googleapis.com/*' => Http::response([
                'purchaseState' => 1, // 1 = Canceled
                'orderId' => 'GPA.0000-1111-2222-33333',
                'purchaseTimeMillis' => '1755648000000',
            ]),
        ]);

        try {
            app(BuyDiamondUseCase::class)->exec(
                $this->sysPlayerId,
                $mstInAppPurchase,
                'Google',
                'GooglePlay',
                'purchase-token-abc',
                'GPA.0000-1111-2222-33333',
                'diamond_500'
            );
            $this->fail('InvalidReceiptException が投げられるべき');
        } catch (InvalidReceiptException $e) {
            $this->assertStringContainsString('not in purchased state', $e->getMessage());
        }

        $this->queryManager->execAllQuery();

        $this->assertNull(
            DB::connection('trx1')->table('trx_diamond')->where('sys_player_id', $this->sysPlayerId)->first()
        );

        // VIPポイントも課金ログも残らない
        $player = DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->first();
        $this->assertSame(0, $player->vip_point);
        $this->assertNull(
            DB::connection('log1')->table('log_in_app_purchase')->where('sys_player_id', $this->sysPlayerId)->first()
        );
    }

    /**
     * VIPポイント付与の対象となるプレイヤーを作る
     */
    private function createPlayer(): void
    {
        DB::connection('sys')->table('sys_player')->insert([
            'id' => $this->sysPlayerId,
            'uuid' => 'test-uuid-buy-diamond',
            'my_id' => 'TEST0001',
            'name' => 'tester',
            'level' => 1,
            'level_exp' => 0,
            'vip_point' => 0,
            'total_paid_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 商品マスターとプラットフォーム商品を作る
     */
    private function createProduct(string $billingPlatform): MstInAppPurchase
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

        $id = DB::connection('mst')->table('mst_in_app_purchase')->insertGetId([
            'deploy_key' => self::DEPLOY_KEY,
            'type' => 'Diamond',
            'paid_diamond_amount' => self::PAID_DIAMOND_AMOUNT,
            'vip_point' => self::VIP_POINT,
            'purchase_limit_reset' => 'None',
            'app_store_product_id' => $billingPlatform === 'AppStore' ? $platformProductId : null,
            'google_play_product_id' => $billingPlatform === 'GooglePlay' ? $platformProductId : null,
            'sort_desc' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return MstInAppPurchase::on('mst')->findOrFail($id);
    }

    private function cleanUp(): void
    {
        DB::connection('trx1')->table('trx_diamond')->delete();
        DB::connection('trx1')->table('trx_diamond_balance')->delete();
        DB::connection('trx1')->table('trx_in_app_purchase')->delete();
        DB::connection('mst')->table('mst_in_app_purchase')->delete();
        DB::connection('mst')->table('mst_billing_platform_product')->delete();
        DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->delete();
        DB::connection('log1')->table('log_in_app_purchase')->delete();
        DB::connection('log1')->table('log_vip_point')->delete();
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
