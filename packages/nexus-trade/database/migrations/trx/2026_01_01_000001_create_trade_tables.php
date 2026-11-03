<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 交換所のトランザクションテーブル
 *
 * trx_trade_product - 商品ごとの交換回数
 *
 * 注意: このマイグレーションは `php artisan trx:migrate` で全シャードに流す。
 * 接続はここで指定せず、実行側が --database で切り替える。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------
        // trx_trade_product: 商品ごとの交換回数
        //
        // trade_count は last_traded_at が属する周期（mst_trade_product.reset_type）の回数。
        // 周期が変わっても行は書き換えず、読むときに「前回の交換が今の周期か」で0とみなす。
        // ------------------------------------------------
        Schema::create('trx_trade_product', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_trade_product_id')->comment('mst_trade_productテーブルのID');
            $table->unsignedInteger('trade_count')->default(0)->comment('last_traded_at の周期での交換回数');
            $table->dateTime('last_traded_at')->comment('最後に交換した日時');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['sys_player_id', 'mst_trade_product_id'], 'pk_trade_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_trade_product');
    }
};
