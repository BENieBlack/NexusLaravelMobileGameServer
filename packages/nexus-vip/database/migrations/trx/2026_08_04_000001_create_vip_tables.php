<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LogDB用VIPログテーブル作成マイグレーション
 * 
 * 注意: このマイグレーションは`php artisan pitr:migrate`で実行してください。
 * PitrMigrateCommandが全LogDBシャード（log1, log2, ...）に対して自動的に実行します。
 */
return new class extends Migration
{
    {
        // ========================================
        // log_vip_point: VIPポイント変動ログ
        // ========================================
        Schema::create('log_vip_point', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->string('unique_request_id')->comment('リクエスト一意ID (log_accessと結合)');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->smallInteger('before_vip_level')->comment('変更前VIPレベル');
            $table->smallInteger('after_vip_level')->comment('変更後VIPレベル');
            $table->unsignedInteger('before_vip_point')->comment('変更前VIPポイント');
            $table->unsignedInteger('after_vip_point')->comment('変更後VIPポイント');
            $table->integer('point_diff')->comment('ポイント増減量');
            $table->string('reason', 100)->comment('変更理由 (purchase, manual_adjustment, campaign)');
            $table->decimal('purchase_amount', 10, 2)->nullable()->comment('課金額（課金起因の場合）');
            $table->string('currency_code', 3)->nullable()->comment('通貨コード (JPY, USD...)');
            $table->string('mst_in_app_purchase_id', 50)->nullable()->comment('アプリ内課金マスターID');
            $table->dateTime('system_at')->comment('APIの日時');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');

            $table->index('sys_player_id');
            $table->index('reason');
            $table->index('system_at');
            $table->index('created_at');
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
