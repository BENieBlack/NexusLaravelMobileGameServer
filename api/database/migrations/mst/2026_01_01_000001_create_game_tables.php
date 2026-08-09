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
        // mst_unit: ユニットマスター
        // ========================================
        Schema::connection('mst')->create('mst_unit', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('ユニットID');
            $table->enum('type', ['Attack', 'Defense', 'Support'])->comment('ユニットタイプ');
            $table->enum('element', ['Fire', 'Water', 'Wind', 'Earth', 'Light', 'Dark'])->comment('属性');
            $table->enum('rarity', ['UR', 'SSR', 'SR', 'R', 'UC', 'C'])->comment('レアリティ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('type');
            $table->index('element');
            $table->index('rarity');
        });

        // ========================================
        // mst_unit__l10n: ユニット多言語
        // ========================================
        Schema::connection('mst')->create('mst_unit__l10n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_unit_id')->comment('ユニットID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('name')->comment('ユニット名');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_unit_id', 'language'], 'pk_unit_language');
            $table->index('deploy_key');
        });

        // ========================================
        // mst_item: アイテムマスター
        // ========================================
        Schema::connection('mst')->create('mst_item', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('アイテムID');
            $table->string('type')->comment('アイテムタイプ');
            $table->string('effect')->comment('効果');
            $table->float('value')->comment('効果値');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
        });

        // ========================================
        // mst_item__l10n: アイテム多言語
        // ========================================
        Schema::connection('mst')->create('mst_item__l10n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_item_id')->comment('アイテムID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('name')->comment('アイテム名');
            $table->text('description')->nullable()->comment('説明');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_item_id', 'language'], 'pk_item_language');
            $table->index('deploy_key');
        });

        // ========================================
        // mst_equipment: 装備マスター
        // ========================================
        Schema::connection('mst')->create('mst_equipment', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('装備ID');
            $table->enum('type', ['Attack', 'Defense', 'Support'])->comment('装備タイプ');
            $table->enum('element', ['Fire', 'Water', 'Wind', 'Earth', 'Light', 'Dark'])->comment('属性');
            $table->enum('rarity', ['UR', 'SSR', 'SR', 'R', 'UC', 'C'])->comment('レアリティ');
            $table->unsignedInteger('attack')->default(0)->comment('攻撃力');
            $table->unsignedInteger('defense')->default(0)->comment('防御力');
            $table->unsignedInteger('hp')->default(0)->comment('HP');
            $table->unsignedInteger('sort_desc')->default(0)->comment('表示順序（降順）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('type');
            $table->index('element');
            $table->index('rarity');
            $table->index('is_active');
            $table->index('sort_desc');
        });

        // ========================================
        // mst_equipment__l10n: 装備多言語
        // ========================================
        Schema::connection('mst')->create('mst_equipment__l10n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_equipment_id')->comment('装備ID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('name')->comment('装備名');
            $table->text('description')->nullable()->comment('説明');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_equipment_id', 'language'], 'pk_equipment_language');
            $table->index('deploy_key');
            $table->index('language');
        });

        // ========================================
        // mst_billing_platform_product: プラットフォーム課金商品
        // ========================================
        Schema::connection('mst')->create('mst_billing_platform_product', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->id()->comment('商品ID');
            $table->string('platform_product_id', 255)->comment('プラットフォーム商品ID');
            $table->enum('billing_platform', ['AppStore', 'GooglePlay', 'PayPal', 'Stripe'])->comment('決済プラットフォーム');
            $table->enum('product_type', ['Consumable', 'NonConsumable', 'Subscription'])->comment('商品種別');
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
            $table->enum('type', ['Diamond', 'Pack', 'Pass'])->comment('課金商品タイプ');
            $table->unsignedInteger('paid_diamond_amount')->default(0)->comment('有償ダイヤ数');
            $table->unsignedInteger('effect_duration_days')->nullable()->comment('効果期間（日数）');
            $table->unsignedInteger('purchase_limit')->nullable()->comment('購入制限回数');
            $table->enum('purchase_limit_reset', ['None', 'Daily', 'Weekly', 'Monthly'])->default('None')->comment('購入制限リセット');
            $table->unsignedBigInteger('app_store_product_id')->nullable()->comment('AppStore商品ID');
            $table->unsignedBigInteger('google_play_product_id')->nullable()->comment('GooglePlay商品ID');
            $table->unsignedInteger('sort_desc')->default(0)->comment('表示順序（降順）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('type');
            $table->index('paid_diamond_amount');
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
            $table->enum('content_type', ['Item', 'Unit', 'FreeDiamond'])->comment('コンテンツタイプ');
            $table->string('content_id')->comment('コンテンツID');
            $table->json('content_option')->nullable()->comment('コンテンツオプション (例: {"grade":1, "level":5})');
            $table->unsignedInteger('content_quantity')->default(1)->comment('1配布あたりのコンテンツ数量');
            $table->unsignedInteger('amount')->default(1)->comment('配布回数（content_quantity × amount = 実際の配布量）');
            $table->unsignedInteger('sort_desc')->default(0)->comment('表示順序（降順）');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_in_app_purchase_id', 'content_type', 'content_id'], 'pk_purchase_content');
            $table->index('deploy_key');
            $table->index('sort_desc');
        });

        // ========================================
        // mst_in_app_purchase_effect: アプリ内課金商品効果
        // ========================================
        Schema::connection('mst')->create('mst_in_app_purchase_effect', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->unsignedBigInteger('mst_in_app_purchase_id')->comment('アプリ内課金商品ID');
            $table->enum('effect_type', ['IdleRewardMultiplier', 'AdSkip', 'ExpBoost', 'GoldBoost', 'DailyMissionBonus'])->comment('効果タイプ');
            $table->decimal('value', 10, 2)->comment('効果値');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            $table->primary(['mst_in_app_purchase_id', 'effect_type'], 'pk_purchase_effect');
            $table->index('deploy_key');
        });

        // ========================================
        // mst_player_level: プレイヤーレベルマスター
        // ========================================
        Schema::connection('mst')->create('mst_player_level', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->unsignedInteger('level')->primary()->comment('レベル');
            $table->unsignedBigInteger('required_exp')->comment('このレベルに到達するために必要な累積経験値');
            $table->unsignedInteger('max_stamina')->comment('このレベルでの最大スタミナ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
        });

        // ========================================
        // mst_unit_level: ユニットレベルマスター
        // ========================================
        Schema::connection('mst')->create('mst_unit_level', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->enum('rarity', ['UR', 'SSR', 'SR', 'R', 'UC', 'C'])->comment('レアリティ');
            $table->unsignedInteger('level')->comment('レベル');
            $table->unsignedInteger('required_exp')->default(0)->comment('このレベルに到達するために必要な累積経験値');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['rarity', 'level'], 'pk_unit_level');
            $table->index('deploy_key');
            $table->index(['rarity', 'level']);
        });

        // ========================================
        // mst_equipment_level: 装備レベルマスター
        // ========================================
        Schema::connection('mst')->create('mst_equipment_level', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->enum('rarity', ['UR', 'SSR', 'SR', 'R', 'UC', 'C'])->comment('レアリティ');
            $table->unsignedInteger('level')->comment('レベル');
            $table->unsignedInteger('required_exp')->default(0)->comment('このレベルに到達するために必要な累積経験値');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['rarity', 'level'], 'pk_equipment_level');
            $table->index('deploy_key');
            $table->index(['rarity', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order
        Schema::connection('mst')->dropIfExists('mst_equipment_level');
        Schema::connection('mst')->dropIfExists('mst_unit_level');
        Schema::connection('mst')->dropIfExists('mst_player_level');
        Schema::connection('mst')->dropIfExists('mst_in_app_purchase_effect');
        Schema::connection('mst')->dropIfExists('mst_in_app_purchase_content');
        Schema::connection('mst')->dropIfExists('mst_in_app_purchase__l10n');
        Schema::connection('mst')->dropIfExists('mst_in_app_purchase');
        Schema::connection('mst')->dropIfExists('mst_billing_platform_product');
        Schema::connection('mst')->dropIfExists('mst_equipment__l10n');
        Schema::connection('mst')->dropIfExists('mst_equipment');
        Schema::connection('mst')->dropIfExists('mst_item__l10n');
        Schema::connection('mst')->dropIfExists('mst_item');
        Schema::connection('mst')->dropIfExists('mst_unit__l10n');
        Schema::connection('mst')->dropIfExists('mst_unit');
    }
};
