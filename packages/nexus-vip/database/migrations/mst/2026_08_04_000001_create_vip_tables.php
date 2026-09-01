<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ========================================
        // mst_vip_level: VIPレベルマスター
        // ========================================
        Schema::connection('mst')->create('mst_vip_level', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('VIPレベルID（vip_0, vip_1, ...）');
            $table->smallInteger('level')->unique()->comment('VIPレベル (0-15)');
            $table->unsignedInteger('required_point')->comment('このレベルに到達するために必要な累積VIPポイント');
            $table->unsignedSmallInteger('max_stamina_bonus')->default(0)->comment('スタミナ上限ボーナス');
            $table->unsignedSmallInteger('daily_diamond_bonus')->default(0)->comment('デイリーダイヤモンドボーナス');
            $table->decimal('shop_discount_rate', 5, 2)->default(0.00)->comment('ショップ割引率 (0.00-1.00)');
            $table->decimal('gacha_discount_rate', 5, 2)->default(0.00)->comment('ガチャ割引率 (0.00-1.00)');
            $table->string('display_badge_url')->nullable()->comment('VIPバッジ画像URL');
            $table->unsignedInteger('sort_desc')->default(0)->comment('表示順序（降順）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('level');
            $table->index('required_point');
        });

        // 初期データ投入
        $vipLevels = [
            ['id' => 'vip_0', 'level' => 0, 'required_point' => 0, 'max_stamina_bonus' => 0, 'daily_diamond_bonus' => 0, 'shop_discount_rate' => 0.00, 'gacha_discount_rate' => 0.00, 'sort_desc' => 0],
            ['id' => 'vip_1', 'level' => 1, 'required_point' => 100, 'max_stamina_bonus' => 10, 'daily_diamond_bonus' => 10, 'shop_discount_rate' => 0.02, 'gacha_discount_rate' => 0.00, 'sort_desc' => 10],
            ['id' => 'vip_2', 'level' => 2, 'required_point' => 500, 'max_stamina_bonus' => 20, 'daily_diamond_bonus' => 20, 'shop_discount_rate' => 0.03, 'gacha_discount_rate' => 0.02, 'sort_desc' => 20],
            ['id' => 'vip_3', 'level' => 3, 'required_point' => 1000, 'max_stamina_bonus' => 30, 'daily_diamond_bonus' => 30, 'shop_discount_rate' => 0.05, 'gacha_discount_rate' => 0.03, 'sort_desc' => 30],
            ['id' => 'vip_4', 'level' => 4, 'required_point' => 3000, 'max_stamina_bonus' => 50, 'daily_diamond_bonus' => 50, 'shop_discount_rate' => 0.07, 'gacha_discount_rate' => 0.05, 'sort_desc' => 40],
            ['id' => 'vip_5', 'level' => 5, 'required_point' => 5000, 'max_stamina_bonus' => 70, 'daily_diamond_bonus' => 70, 'shop_discount_rate' => 0.10, 'gacha_discount_rate' => 0.07, 'sort_desc' => 50],
            ['id' => 'vip_6', 'level' => 6, 'required_point' => 10000, 'max_stamina_bonus' => 100, 'daily_diamond_bonus' => 100, 'shop_discount_rate' => 0.12, 'gacha_discount_rate' => 0.10, 'sort_desc' => 60],
            ['id' => 'vip_7', 'level' => 7, 'required_point' => 20000, 'max_stamina_bonus' => 120, 'daily_diamond_bonus' => 120, 'shop_discount_rate' => 0.15, 'gacha_discount_rate' => 0.12, 'sort_desc' => 70],
            ['id' => 'vip_8', 'level' => 8, 'required_point' => 30000, 'max_stamina_bonus' => 150, 'daily_diamond_bonus' => 150, 'shop_discount_rate' => 0.17, 'gacha_discount_rate' => 0.15, 'sort_desc' => 80],
            ['id' => 'vip_9', 'level' => 9, 'required_point' => 50000, 'max_stamina_bonus' => 180, 'daily_diamond_bonus' => 180, 'shop_discount_rate' => 0.20, 'gacha_discount_rate' => 0.17, 'sort_desc' => 90],
            ['id' => 'vip_10', 'level' => 10, 'required_point' => 100000, 'max_stamina_bonus' => 200, 'daily_diamond_bonus' => 200, 'shop_discount_rate' => 0.25, 'gacha_discount_rate' => 0.20, 'sort_desc' => 100],
        ];

        foreach ($vipLevels as $level) {
            DB::connection('mst')->table('mst_vip_level')->insert(array_merge($level, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // ========================================
        // mst_vip_level_reward: VIPレベルアップ報酬マスター
        // ========================================
        Schema::connection('mst')->create('mst_vip_level_reward', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->smallInteger('vip_level')->comment('VIPレベル');
            $table->enum('content_type', ['item', 'unit', 'equipment', 'diamond', 'wallet', 'stamina'])->comment('コンテンツタイプ');
            $table->string('content_mst_id')->comment('コンテンツID (mst_item_id等、diamond/stamina/walletはダミー値)');
            $table->json('content_option')->nullable()->comment('コンテンツオプション (例: {"grade":1, "level":5})');
            $table->unsignedInteger('content_quantity')->default(1)->comment('1配布あたりのコンテンツ数量');
            $table->unsignedInteger('amount')->default(1)->comment('配布回数（content_quantity × amount = 実際の配布量）');
            $table->boolean('is_paid')->default(false)->comment('有償フラグ（wallet/diamondの場合）');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['vip_level', 'content_type', 'content_mst_id'], 'pk_vip_level_reward');
            $table->index('deploy_key');
            $table->index(['vip_level', 'sort_order']);
            $table->index('is_active');
        });

        // VIPレベル報酬初期データ投入
        $vipRewards = [
            // VIP1報酬: 無償ダイヤ100個
            ['vip_level' => 1, 'content_type' => 'diamond', 'content_mst_id' => 'free', 'content_option' => null, 'content_quantity' => 100, 'amount' => 1, 'is_paid' => false, 'sort_order' => 1],

            // VIP2報酬: 無償ダイヤ200個 + スタミナ50
            ['vip_level' => 2, 'content_type' => 'diamond', 'content_mst_id' => 'free', 'content_option' => null, 'content_quantity' => 200, 'amount' => 1, 'is_paid' => false, 'sort_order' => 1],
            ['vip_level' => 2, 'content_type' => 'stamina', 'content_mst_id' => 'stamina', 'content_option' => null, 'content_quantity' => 50, 'amount' => 1, 'is_paid' => false, 'sort_order' => 2],

            // VIP3報酬: 無償ダイヤ300個
            ['vip_level' => 3, 'content_type' => 'diamond', 'content_mst_id' => 'free', 'content_option' => null, 'content_quantity' => 300, 'amount' => 1, 'is_paid' => false, 'sort_order' => 1],

            // VIP4報酬: 無償ダイヤ500個 + スタミナ100
            ['vip_level' => 4, 'content_type' => 'diamond', 'content_mst_id' => 'free', 'content_option' => null, 'content_quantity' => 500, 'amount' => 1, 'is_paid' => false, 'sort_order' => 1],
            ['vip_level' => 4, 'content_type' => 'stamina', 'content_mst_id' => 'stamina', 'content_option' => null, 'content_quantity' => 100, 'amount' => 1, 'is_paid' => false, 'sort_order' => 2],

            // VIP5報酬: 無償ダイヤ1000個
            ['vip_level' => 5, 'content_type' => 'diamond', 'content_mst_id' => 'free', 'content_option' => null, 'content_quantity' => 1000, 'amount' => 1, 'is_paid' => false, 'sort_order' => 1],

            // VIP6報酬: 無償ダイヤ1500個 + スタミナ150
            ['vip_level' => 6, 'content_type' => 'diamond', 'content_mst_id' => 'free', 'content_option' => null, 'content_quantity' => 1500, 'amount' => 1, 'is_paid' => false, 'sort_order' => 1],
            ['vip_level' => 6, 'content_type' => 'stamina', 'content_mst_id' => 'stamina', 'content_option' => null, 'content_quantity' => 150, 'amount' => 1, 'is_paid' => false, 'sort_order' => 2],

            // VIP7報酬: 無償ダイヤ2000個
            ['vip_level' => 7, 'content_type' => 'diamond', 'content_mst_id' => 'free', 'content_option' => null, 'content_quantity' => 2000, 'amount' => 1, 'is_paid' => false, 'sort_order' => 1],

            // VIP8報酬: 無償ダイヤ3000個 + スタミナ200
            ['vip_level' => 8, 'content_type' => 'diamond', 'content_mst_id' => 'free', 'content_option' => null, 'content_quantity' => 3000, 'amount' => 1, 'is_paid' => false, 'sort_order' => 1],
            ['vip_level' => 8, 'content_type' => 'stamina', 'content_mst_id' => 'stamina', 'content_option' => null, 'content_quantity' => 200, 'amount' => 1, 'is_paid' => false, 'sort_order' => 2],

            // VIP9報酬: 無償ダイヤ5000個
            ['vip_level' => 9, 'content_type' => 'diamond', 'content_mst_id' => 'free', 'content_option' => null, 'content_quantity' => 5000, 'amount' => 1, 'is_paid' => false, 'sort_order' => 1],

            // VIP10報酬（最高レベル）: 無償ダイヤ10000個 + スタミナ300
            ['vip_level' => 10, 'content_type' => 'diamond', 'content_mst_id' => 'free', 'content_option' => null, 'content_quantity' => 10000, 'amount' => 1, 'is_paid' => false, 'sort_order' => 1],
            ['vip_level' => 10, 'content_type' => 'stamina', 'content_mst_id' => 'stamina', 'content_option' => null, 'content_quantity' => 300, 'amount' => 1, 'is_paid' => false, 'sort_order' => 2],
        ];

        foreach ($vipRewards as $reward) {
            DB::connection('mst')->table('mst_vip_level_reward')->insert(array_merge($reward, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mst')->dropIfExists('mst_vip_level_reward');
        Schema::connection('mst')->dropIfExists('mst_vip_level');
    }
};
