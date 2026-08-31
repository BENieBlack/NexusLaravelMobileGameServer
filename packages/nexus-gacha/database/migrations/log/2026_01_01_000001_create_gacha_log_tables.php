<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LogDB用ガチャログテーブル作成マイグレーション
 * 
 * 注意: このマイグレーションは`php artisan pitr:migrate`で実行してください。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // log_trx_gacha: ガチャ進行状況変更ログ
        // ========================================
        Schema::create('log_trx_gacha', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            // trx_gacha は採番idを持たず (sys_player_id, mst_gacha_id) で一意。
            // 対象行はこの2つで特定する（log_trx_wallet と同じ形）
            $table->string('mst_gacha_id')->nullable()->comment('マスターガチャID');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');
            
            $table->json('before_data')->nullable()->comment('変更前データ');
            $table->json('after_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');
            
            $table->unsignedInteger('current_step')->nullable()->comment('現在のステップ');
            $table->unsignedInteger('daily_draw_count')->nullable()->comment('本日の実行回数');
            $table->unsignedInteger('total_draw_count')->nullable()->comment('累計実行回数');
            
            $table->string('reason', 100)->nullable()->comment('変更理由 (draw, step_advance, daily_reset, period_reset, admin_reset)');
            $table->json('metadata')->nullable()->comment('メタデータ');
            
            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            
            $table->index('sys_player_id');
            $table->index('mst_gacha_id');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_trx_gacha');
    }
};
