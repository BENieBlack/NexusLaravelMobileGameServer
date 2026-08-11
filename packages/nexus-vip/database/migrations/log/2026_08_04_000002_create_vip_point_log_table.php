<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LogDB用VIPポイント変動ログテーブル作成マイグレーション
 *
 * 注意: このマイグレーションは`php artisan pitr:migrate`で実行してください。
 * PitrMigrateCommandが全LogDBシャード（log1, log2, ...）に対して自動的に実行します。
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('log_vip_point', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->string('unique_request_id')->comment('リクエスト一意ID (log_accessと結合)');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->unsignedTinyInteger('before_vip_level')->comment('変更前VIPレベル');
            $table->unsignedTinyInteger('after_vip_level')->comment('変更後VIPレベル');
            $table->unsignedInteger('before_vip_point')->comment('変更前累積VIPポイント');
            $table->unsignedInteger('after_vip_point')->comment('変更後累積VIPポイント');
            $table->integer('point_diff')->comment('変動ポイント数');
            $table->string('reason', 64)->comment('変動理由');
            $table->decimal('purchase_amount', 15, 2)->nullable()->comment('課金額（課金起因の場合）');
            $table->string('currency_code', 8)->nullable()->comment('通貨コード（課金起因の場合）');
            $table->string('mst_in_app_purchase_id')->nullable()->comment('課金商品ID（課金起因の場合）');
            $table->dateTime('system_at')->comment('システム日時');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');

            $table->index('unique_request_id');
            $table->index('sys_player_id');
            $table->index('system_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_vip_point');
    }
};
