<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 交換所のログテーブル
 *
 * log_action_trade - 商品を交換した記録（1回の交換につき1行。追記のみ）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_action_trade', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->string('unique_request_id')->comment('リクエスト一意ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_trade_product_id')->comment('mst_trade_productテーブルのID');
            $table->unsignedInteger('quantity')->comment('交換した個数');
            $table->string('cost_type')->comment('消費種別 (diamond / paid_diamond / item / wallet)');
            $table->string('cost_mst_id')->nullable()->comment('消費したアイテム・通貨のmst_item.id');
            $table->unsignedInteger('cost_amount')->comment('消費した合計（消費個数 × 交換した個数）');
            $table->dateTime('system_at')->comment('システム日時');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');

            $table->index('unique_request_id');
            $table->index('sys_player_id');
            $table->index('system_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_action_trade');
    }
};
