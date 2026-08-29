<?php

namespace Tests\Feature\Domain\InAppPurchase\UseCases;

use App\Models\Mst\MstInAppPurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * 課金購入テストの共通フィクスチャ
 *
 * 商品マスター・プレイヤー・Google Playの応答など、
 * 商品タイプによらず同じ準備をまとめる。
 */
trait PreparesPurchaseFixtures
{
    /**
     * VIP付与と課金ログが購入と同じトランザクションで確定していること
     */
    private function assertVipPointAndLogsRecorded(): void
    {
        $player = DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->first();
        $this->assertSame(self::VIP_POINT, $player->vip_point);

        $log = DB::connection('log1')->table('log_in_app_purchase')
            ->where('sys_player_id', $this->sysPlayerId)->first();
        $this->assertNotNull($log);
        $this->assertSame('Purchased', $log->status);
        $this->assertSame('980.00', (string) $log->pay_amount);
    }

    private function fakeGooglePlay(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'androidpublisher.googleapis.com/*' => Http::response([
                'purchaseState' => 0,
                'consumptionState' => 0,
                'orderId' => 'GPA.TEST',
                'purchaseTimeMillis' => '1755648000000',
                'quantity' => 1,
                'priceAmountMicros' => (string) self::PRICE_MICROS,
                'priceCurrencyCode' => 'JPY',
            ]),
        ]);
    }

    private function createProduct(
        string $type,
        int $paidDiamondAmount = 0,
        ?int $effectDurationDays = null,
        ?int $purchaseLimit = null,
        string $purchaseLimitReset = 'None',
    ): MstInAppPurchase {
        $platformProductId = DB::connection('mst')->table('mst_billing_platform_product')->insertGetId([
            'deploy_key' => self::DEPLOY_KEY,
            'platform_product_id' => strtolower($type),
            'billing_platform' => 'GooglePlay',
            'product_type' => 'Consumable',
            'price_amount_micros' => self::PRICE_MICROS,
            'price_currency_code' => 'JPY',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::connection('mst')->table('mst_in_app_purchase')->insertGetId([
            'deploy_key' => self::DEPLOY_KEY,
            'type' => $type,
            'paid_diamond_amount' => $paidDiamondAmount,
            'vip_point' => self::VIP_POINT,
            'effect_duration_days' => $effectDurationDays,
            'purchase_limit' => $purchaseLimit,
            'purchase_limit_reset' => $purchaseLimitReset,
            'google_play_product_id' => $platformProductId,
            'sort_desc' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return MstInAppPurchase::on('mst')->findOrFail($id);
    }

    private function createPackContents(int $mstInAppPurchaseId): void
    {
        DB::connection('mst')->table('mst_in_app_purchase_content')->insert([
            [
                'deploy_key' => self::DEPLOY_KEY,
                'mst_in_app_purchase_id' => $mstInAppPurchaseId,
                'content_type' => 'FreeDiamond',
                'content_mst_id' => 'free_diamond',
                'content_quantity' => 1,
                'amount' => 300,
                'sort_desc' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'deploy_key' => self::DEPLOY_KEY,
                'mst_in_app_purchase_id' => $mstInAppPurchaseId,
                'content_type' => 'Item',
                'content_mst_id' => 'item_potion_001',
                'content_quantity' => 1,
                'amount' => 5,
                'sort_desc' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function createPassEffect(int $mstInAppPurchaseId): void
    {
        DB::connection('mst')->table('mst_in_app_purchase_effect')->insert([
            'deploy_key' => self::DEPLOY_KEY,
            'mst_in_app_purchase_id' => $mstInAppPurchaseId,
            'effect_type' => 'ExpBoost',
            'value' => 1.50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPlayer(): void
    {
        DB::connection('sys')->table('sys_player')->insert([
            'id' => $this->sysPlayerId,
            'uuid' => 'test-uuid-buy-pack-pass',
            'my_id' => 'TEST0002',
            'name' => 'tester',
            'level' => 1,
            'level_exp' => 0,
            'vip_point' => 0,
            'total_paid_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function cleanUp(): void
    {
        foreach (['trx_diamond', 'trx_diamond_balance', 'trx_item', 'trx_unit', 'trx_in_app_purchase', 'trx_in_app_purchase_effect'] as $table) {
            DB::connection('trx1')->table($table)->delete();
        }
        DB::connection('log1')->table('log_in_app_purchase')->delete();
        DB::connection('log1')->table('log_vip_point')->delete();
        DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->delete();
        foreach (['mst_in_app_purchase', 'mst_billing_platform_product', 'mst_in_app_purchase_content', 'mst_in_app_purchase_effect'] as $table) {
            DB::connection('mst')->table($table)->delete();
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
