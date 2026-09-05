<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * サポートする言語コード
     */
    protected $supportedLanguages = ['ja', 'en', 'zh-TW', 'zh-CN', 'hi', 'es', 'fr', 'ar', 'id', 'pt', 'bn', 'ru', 'de', 'ko'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ========================================
        // mst_billing_platform_product: プラットフォーム課金商品
        // ========================================
        Schema::connection('mst')->create('mst_billing_platform_product', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->id()->comment('商品ID');
            $table->string('platform_product_id', 255)->comment('プラットフォーム商品ID');
            $table->enum('billing_platform', ['app_store', 'google_play', 'pay_pal', 'stripe'])->comment('決済プラットフォーム');
            $table->enum('product_type', ['consumable', 'non_consumable', 'subscription'])->comment('商品種別');
            $table->unsignedBigInteger('price_amount_micros')->nullable()->comment('価格（マイクロ単位、例: 1,000,000 = 1.00 USD）');
            $table->string('price_currency_code', 3)->nullable()->comment('通貨コード（ISO 4217、例: USD, JPY）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->unique(['billing_platform', 'platform_product_id'], 'uk_billing_platform_product');
            $table->index('billing_platform');
            $table->index('is_active');
            $table->index(['billing_platform', 'is_active']);
        });

        // ========================================
        // mst_in_app_purchase: アプリ内課金商品
        // ========================================
        Schema::connection('mst')->create('mst_in_app_purchase', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->id()->comment('アプリ内課金商品ID');
            $table->enum('type', ['diamond', 'pack', 'pass'])->comment('課金商品タイプ');
            $table->unsignedInteger('paid_diamond_amount')->default(0)->comment('有償ダイヤ数');
            $table->unsignedInteger('vip_point')->default(0)->comment('付与VIPポイント');
            $table->unsignedInteger('effect_duration_days')->nullable()->comment('効果期間（日数）');
            $table->unsignedInteger('purchase_limit')->nullable()->comment('購入制限回数');
            $table->enum('purchase_limit_reset', ['none', 'daily', 'weekly', 'monthly'])->default('none')->comment('購入制限リセット');
            $table->unsignedBigInteger('app_store_product_id')->nullable()->comment('AppStore商品ID');
            $table->unsignedBigInteger('google_play_product_id')->nullable()->comment('GooglePlay商品ID');
            $table->unsignedInteger('sort_desc')->default(0)->comment('表示順序（降順）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('type');
            $table->index('paid_diamond_amount');
            $table->index('vip_point');
            $table->index('app_store_product_id');
            $table->index('google_play_product_id');
            $table->index('is_active');
            $table->index('sort_desc');
        });

        // ========================================
        // mst_in_app_purchase__l10n: アプリ内課金商品多言語
        // ========================================
        Schema::connection('mst')->create('mst_in_app_purchase__l10n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->unsignedBigInteger('mst_in_app_purchase_id')->comment('アプリ内課金商品ID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('name')->comment('商品名');
            $table->text('description')->nullable()->comment('説明');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_in_app_purchase_id', 'language'], 'pk_in_app_purchase_language');
            $table->index('deploy_key');
        });

        // ========================================
        // mst_in_app_purchase_content: アプリ内課金商品コンテンツ
        // ========================================
        Schema::connection('mst')->create('mst_in_app_purchase_content', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->unsignedBigInteger('mst_in_app_purchase_id')->comment('アプリ内課金商品ID');
            $table->enum('content_type', ['item', 'unit', 'free_diamond'])->comment('コンテンツタイプ');
            $table->string('content_mst_id')->comment('コンテンツID');
            $table->json('content_option')->nullable()->comment('コンテンツオプション (例: {"grade":1, "level":5})');
            $table->unsignedInteger('content_quantity')->default(1)->comment('1配布あたりのコンテンツ数量');
            $table->unsignedInteger('amount')->default(1)->comment('配布回数（content_quantity × amount = 実際の配布量）');
            $table->unsignedInteger('sort_desc')->default(0)->comment('表示順序（降順）');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_in_app_purchase_id', 'content_type', 'content_mst_id'], 'pk_purchase_content');
            $table->index('deploy_key');
            $table->index('sort_desc');
        });

        // ========================================
        // mst_in_app_purchase_effect: アプリ内課金商品効果
        // ========================================
        Schema::connection('mst')->create('mst_in_app_purchase_effect', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->unsignedBigInteger('mst_in_app_purchase_id')->comment('アプリ内課金商品ID');
            $table->enum('effect_type', ['idle_reward_multiplier', 'ad_skip', 'exp_boost', 'gold_boost', 'daily_mission_bonus'])->comment('効果タイプ');
            $table->decimal('value', 10, 2)->comment('効果値');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_in_app_purchase_id', 'effect_type'], 'pk_purchase_effect');
            $table->index('deploy_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mst')->dropIfExists('mst_in_app_purchase_effect');
        Schema::connection('mst')->dropIfExists('mst_in_app_purchase_content');
        Schema::connection('mst')->dropIfExists('mst_in_app_purchase__l10n');
        Schema::connection('mst')->dropIfExists('mst_in_app_purchase');
        Schema::connection('mst')->dropIfExists('mst_billing_platform_product');
    }
};
