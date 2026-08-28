<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LogDB用VIPログテーブル作成マイグレーション
 * 
 * 注意: このマイグレーションは`php artisan pitr:migrate`で実行してください。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // log_trx_vip_login_bonus_history: VIPログインボーナス履歴変更ログ
        // ========================================
        Schema::create('log_trx_vip_login_bonus_history', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->unsignedBigInteger('trx_vip_login_bonus_history_id')->nullable()->comment('trx_vip_login_bonus_historyテーブルのID');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');
            
            $table->json('before_data')->nullable()->comment('変更前データ');
            $table->json('after_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');
            
            $table->string('mst_vip_login_bonus_id', 64)->nullable()->comment('VIPログインボーナスID');
            $table->unsignedInteger('day')->nullable()->comment('受け取り日数');
            $table->unsignedTinyInteger('vip_level')->nullable()->comment('VIPレベル');
            $table->dateTime('received_at')->nullable()->comment('受け取り日時');
            
            $table->string('reason', 100)->nullable()->comment('変更理由 (grant, admin_correction, admin_delete)');
            $table->json('metadata')->nullable()->comment('メタデータ');
            
            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            
            $table->index('sys_player_id');
            // 自動生成名だと64文字を超えるため明示的に付ける
            $table->index('trx_vip_login_bonus_history_id', 'idx_vip_login_bonus_history_id');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_trx_vip_login_bonus_history');
    }
};
