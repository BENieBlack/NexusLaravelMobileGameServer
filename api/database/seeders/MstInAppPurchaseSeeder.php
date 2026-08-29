<?php

namespace Database\Seeders;

use App\Models\Mst\MstBillingPlatformProduct;
use App\Models\Mst\MstInAppPurchase;
use App\Models\Mst\MstInAppPurchaseContent;
use App\Models\Mst\MstInAppPurchaseEffect;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\Mst\_BaseMst;

class MstInAppPurchaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // マスターデータの投入なので書き込みを許可する
        _BaseMst::allowWrites();

        // 既存データをクリア（外部キー制約がないため順序は自由）
        DB::connection('mst')->table('mst_in_app_purchase_effect')->truncate();
        DB::connection('mst')->table('mst_in_app_purchase_content')->truncate();
        DB::connection('mst')->table('mst_in_app_purchase')->truncate();

        // プラットフォーム商品IDを取得
        $appStoreDiamond100 = MstBillingPlatformProduct::where('billing_platform', 'AppStore')
            ->where('platform_product_id', 'com.example.game.diamond100')
            ->first();
        $googlePlayDiamond100 = MstBillingPlatformProduct::where('billing_platform', 'GooglePlay')
            ->where('platform_product_id', 'com.example.game.diamond100')
            ->first();

        $appStoreDiamond500 = MstBillingPlatformProduct::where('billing_platform', 'AppStore')
            ->where('platform_product_id', 'com.example.game.diamond500')
            ->first();
        $googlePlayDiamond500 = MstBillingPlatformProduct::where('billing_platform', 'GooglePlay')
            ->where('platform_product_id', 'com.example.game.diamond500')
            ->first();

        $appStoreDiamond1000 = MstBillingPlatformProduct::where('billing_platform', 'AppStore')
            ->where('platform_product_id', 'com.example.game.diamond1000')
            ->first();
        $googlePlayDiamond1000 = MstBillingPlatformProduct::where('billing_platform', 'GooglePlay')
            ->where('platform_product_id', 'com.example.game.diamond1000')
            ->first();

        $appStoreStarterPack = MstBillingPlatformProduct::where('billing_platform', 'AppStore')
            ->where('platform_product_id', 'com.example.game.starter_pack')
            ->first();
        $googlePlayStarterPack = MstBillingPlatformProduct::where('billing_platform', 'GooglePlay')
            ->where('platform_product_id', 'com.example.game.starter_pack')
            ->first();

        $appStoreMonthlyPass = MstBillingPlatformProduct::where('billing_platform', 'AppStore')
            ->where('platform_product_id', 'com.example.game.monthly_pass')
            ->first();
        $googlePlayMonthlyPass = MstBillingPlatformProduct::where('billing_platform', 'GooglePlay')
            ->where('platform_product_id', 'com.example.game.monthly_pass')
            ->first();

        // 1. Diamond商品（有償ダイアモンドのみ）
        MstInAppPurchase::create([
            'deploy_key' => 202601010,
            'type' => 'Diamond',
            'paid_diamond_amount' => 100,
            'effect_duration_days' => null, // Diamond商品は効果なし
            'purchase_limit' => null, // 無制限
            'purchase_limit_reset' => 'None',
            'app_store_product_id' => $appStoreDiamond100->id,
            'google_play_product_id' => $googlePlayDiamond100->id,
            'sort_desc' => 300,
            'is_active' => true,
        ]);

        MstInAppPurchase::create([
            'deploy_key' => 202601010,
            'type' => 'Diamond',
            'paid_diamond_amount' => 500,
            'effect_duration_days' => null,
            'purchase_limit' => null,
            'purchase_limit_reset' => 'None',
            'app_store_product_id' => $appStoreDiamond500->id,
            'google_play_product_id' => $googlePlayDiamond500->id,
            'sort_desc' => 200,
            'is_active' => true,
        ]);

        MstInAppPurchase::create([
            'deploy_key' => 202601010,
            'type' => 'Diamond',
            'paid_diamond_amount' => 1000,
            'effect_duration_days' => null,
            'purchase_limit' => null,
            'purchase_limit_reset' => 'None',
            'app_store_product_id' => $appStoreDiamond1000->id,
            'google_play_product_id' => $googlePlayDiamond1000->id,
            'sort_desc' => 100,
            'is_active' => true,
        ]);

        // 2. Pack商品（有償ダイアモンド + 無償ダイアモンド + アイテム）
        $starterPack = MstInAppPurchase::create([
            'deploy_key' => 202601010,
            'type' => 'Pack',
            'paid_diamond_amount' => 300,
            'effect_duration_days' => null, // Pack商品は効果なし
            'purchase_limit' => 1, // 1回のみ購入可能
            'purchase_limit_reset' => 'None', // リセットなし
            'app_store_product_id' => $appStoreStarterPack->id,
            'google_play_product_id' => $googlePlayStarterPack->id,
            'sort_desc' => 500,
            'is_active' => true,
        ]);

        // Pack商品のコンテンツ（無償ダイアモンド）
        MstInAppPurchaseContent::create([
            'deploy_key' => 202601010,
            'mst_in_app_purchase_id' => $starterPack->id,
            'content_type' => 'FreeDiamond',
            'content_mst_id' => 'diamond', // FreeDiamondの場合は'diamond'
            'amount' => 500,
            'sort_desc' => 300,
        ]);

        // Pack商品のコンテンツ（アイテム）
        // 注: mst_itemテーブルにデータが存在する場合のみ有効
        // MstInAppPurchaseContent::create([
        //     'mst_in_app_purchase_id' => $starterPack->id,
        //     'content_type' => 'Item',
        //     'content_mst_id' => 'item_001', // mst_item.id
        //     'amount' => 5,
        //     'sort_desc' => 200,
        // ]);

        // 3. Pass商品（有償ダイアモンド + 継続効果）
        $monthlyPass = MstInAppPurchase::create([
            'deploy_key' => 202601010,
            'type' => 'Pass',
            'paid_diamond_amount' => 500,
            'effect_duration_days' => 30, // 30日間有効
            'purchase_limit' => 1, // 1回のみ購入可能
            'purchase_limit_reset' => 'Monthly', // 毎月リセット
            'app_store_product_id' => $appStoreMonthlyPass->id,
            'google_play_product_id' => $googlePlayMonthlyPass->id,
            'sort_desc' => 600,
            'is_active' => true,
        ]);

        // Pass商品のコンテンツ（継続的に付与される無償ダイアモンド）
        MstInAppPurchaseContent::create([
            'deploy_key' => 202601010,
            'mst_in_app_purchase_id' => $monthlyPass->id,
            'content_type' => 'FreeDiamond',
            'content_mst_id' => 'diamond',
            'amount' => 100, // 1日あたり100個
            'sort_desc' => 100,
        ]);

        // Pass商品の効果
        MstInAppPurchaseEffect::create([
            'deploy_key' => 202601010,
            'mst_in_app_purchase_id' => $monthlyPass->id,
            'effect_type' => 'IdleRewardMultiplier',
            'value' => 2.0, // 放置報酬2倍
        ]);

        MstInAppPurchaseEffect::create([
            'deploy_key' => 202601010,
            'mst_in_app_purchase_id' => $monthlyPass->id,
            'effect_type' => 'AdSkip',
            'value' => 1, // 広告スキップ有効
        ]);

        MstInAppPurchaseEffect::create([
            'deploy_key' => 202601010,
            'mst_in_app_purchase_id' => $monthlyPass->id,
            'effect_type' => 'ExpBoost',
            'value' => 1.5, // 経験値1.5倍
        ]);
    }
}
