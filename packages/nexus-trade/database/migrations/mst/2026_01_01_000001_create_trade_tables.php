<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 交換所のマスターテーブル群
 *
 * mst_trade                    - 交換所（通常交換所、イベント交換所など。商品を並べる場所）
 * mst_trade__l10n              - 交換所の表示名（多言語）
 * mst_trade_product            - 商品（消費種別と消費個数・交換回数の上限・交換期間）
 * mst_trade_product__l10n      - 商品の表示名・説明（多言語）
 * mst_trade_product_content    - 商品を交換すると手に入るもの
 */
return new class extends Migration
{
    protected $supportedLanguages = ['ja', 'en', 'zh-TW', 'zh-CN', 'hi', 'es', 'fr', 'ar', 'id', 'pt', 'bn', 'ru', 'de', 'ko'];

    public function up(): void
    {
        // ------------------------------------------------
        // mst_trade
        // 期間外の交換所の商品は、商品の期間内でも交換できない
        // ------------------------------------------------
        Schema::create('mst_trade', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('交換所ID（例: trade_general, trade_event_202605）');
            $table->dateTime('start_at')->comment('開始日時');
            $table->dateTime('end_at')->nullable()->comment('終了日時（NULLは無期限）');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
        });

        Schema::create('mst_trade__l10n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_trade_id')->comment('mst_tradeテーブルのID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('name')->comment('交換所名');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_trade_id', 'language'], 'pk_trade_language');
            $table->index('deploy_key');
        });

        // ------------------------------------------------
        // mst_trade_product
        // 1回交換するごとに cost_type の cost_mst_id を cost_amount 個消費する。trade_limit 回まで交換でき、reset_type の周期で数え直す
        // ------------------------------------------------
        Schema::create('mst_trade_product', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('商品ID（例: trade_general_potion_daily）');
            $table->string('mst_trade_id')->comment('mst_tradeテーブルのID');
            $table->string('cost_type')->comment('消費種別 (diamond: 無償→有償の順 / paid_diamond: 有償のみ / item: アイテム / wallet: コイン・ゴールドなどの通貨)');
            $table->string('cost_mst_id')->nullable()->comment('消費するアイテム・通貨のmst_item.id（cost_type が item / wallet のとき）');
            $table->unsignedInteger('cost_amount')->comment('1回の交換で消費する個数');
            $table->unsignedInteger('trade_limit')->nullable()->comment('交換回数の上限（NULLは無制限）');
            $table->string('reset_type')->default('none')->comment('交換回数を数え直す周期 (none / daily / weekly / monthly。ゲーム内日付の境界で切り替える)');
            $table->dateTime('start_at')->comment('交換開始日時');
            $table->dateTime('end_at')->nullable()->comment('交換終了日時（NULLは無期限）');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('mst_trade_id');
        });

        Schema::create('mst_trade_product__l10n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_trade_product_id')->comment('mst_trade_productテーブルのID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('name')->comment('商品名');
            $table->text('description')->nullable()->comment('商品の説明');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_trade_product_id', 'language'], 'pk_trade_product_language');
            $table->index('deploy_key');
        });

        // ------------------------------------------------
        // mst_trade_product_content
        // 1つの商品で複数のものを手に入れられる（セット商品）
        // ------------------------------------------------
        Schema::create('mst_trade_product_content', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_trade_product_id')->comment('mst_trade_productテーブルのID');
            $table->string('content_type')->comment('手に入るものの種別 (item / unit / equipment / diamond / stamina など)');
            $table->string('content_mst_id')->comment('手に入るもののマスターID（diamond/staminaはダミー値）');
            $table->unsignedInteger('content_quantity')->default(1)->comment('1回あたりの数量');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_trade_product_id', 'content_type', 'content_mst_id'], 'pk_trade_product_content');
            $table->index('deploy_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_trade_product_content');
        Schema::dropIfExists('mst_trade_product__l10n');
        Schema::dropIfExists('mst_trade_product');
        Schema::dropIfExists('mst_trade__l10n');
        Schema::dropIfExists('mst_trade');
    }
};
