<?php

namespace Database\Seeders;

use App\Models\Mst\MstBillingPlatformProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstBillingPlatformProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 既存データをクリア
        DB::connection('mst')->table('mst_billing_platform_product')->truncate();

        $products = [
            // AppStore商品
            [
                'deploy_key' => 202601010,
                'platform_product_id' => 'com.example.game.diamond100',
                'billing_platform' => 'AppStore',
                'product_type' => 'Consumable',
                'is_active' => true,
            ],
            [
                'deploy_key' => 202601010,
                'platform_product_id' => 'com.example.game.diamond500',
                'billing_platform' => 'AppStore',
                'product_type' => 'Consumable',
                'is_active' => true,
            ],
            [
                'deploy_key' => 202601010,
                'platform_product_id' => 'com.example.game.diamond1000',
                'billing_platform' => 'AppStore',
                'product_type' => 'Consumable',
                'is_active' => true,
            ],
            [
                'deploy_key' => 202601010,
                'platform_product_id' => 'com.example.game.starter_pack',
                'billing_platform' => 'AppStore',
                'product_type' => 'Consumable',
                'is_active' => true,
            ],
            [
                'deploy_key' => 202601010,
                'platform_product_id' => 'com.example.game.monthly_pass',
                'billing_platform' => 'AppStore',
                'product_type' => 'Subscription',
                'is_active' => true,
            ],

            // GooglePlay商品
            [
                'deploy_key' => 202601010,
                'platform_product_id' => 'com.example.game.diamond100',
                'billing_platform' => 'GooglePlay',
                'product_type' => 'Consumable',
                'is_active' => true,
            ],
            [
                'deploy_key' => 202601010,
                'platform_product_id' => 'com.example.game.diamond500',
                'billing_platform' => 'GooglePlay',
                'product_type' => 'Consumable',
                'is_active' => true,
            ],
            [
                'deploy_key' => 202601010,
                'platform_product_id' => 'com.example.game.diamond1000',
                'billing_platform' => 'GooglePlay',
                'product_type' => 'Consumable',
                'is_active' => true,
            ],
            [
                'deploy_key' => 202601010,
                'platform_product_id' => 'com.example.game.starter_pack',
                'billing_platform' => 'GooglePlay',
                'product_type' => 'Consumable',
                'is_active' => true,
            ],
            [
                'deploy_key' => 202601010,
                'platform_product_id' => 'com.example.game.monthly_pass',
                'billing_platform' => 'GooglePlay',
                'product_type' => 'Subscription',
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            MstBillingPlatformProduct::create($product);
        }
    }
}
