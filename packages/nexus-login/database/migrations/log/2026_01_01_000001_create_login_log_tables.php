<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LogDB用ログインボーナスログテーブル作成マイグレーション
 * 
 * 注意: このマイグレーションは`php artisan pitr:migrate`で実行してください。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // log_trx_login_bonus_history: ログインボーナス履歴変更ログ
        // ========================================
        Schema::create('log_trx_login_bonus_history', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->unsignedBigInteger('history_id')->nullable()->comment('履歴ID');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');
            
            $table->json('before_data')->nullable()->comment('変更前データ');
            $table->json('after_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');
            
            $table->string('mst_login_bonus_id')->nullable()->comment('ログインボーナスID');
            $table->date('received_date')->nullable()->comment('受け取り日付');
            $table->enum('reward_type', ['item', 'unit', 'equipment', 'wallet', 'diamond'])->nullable()->comment('報酬タイプ');
            $table->string('reward_id')->nullable()->comment('報酬ID');
            $table->unsignedInteger('reward_amount')->nullable()->comment('報酬数量');
            
            $table->string('reason', 100)->nullable()->comment('変更理由 (grant, admin_correction, admin_delete)');
            $table->json('metadata')->nullable()->comment('メタデータ');
            
            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID');
            $table->dateTime('created_at')->useCurrent()->comment('作成日時');
            
            $table->index('sys_player_id');
            $table->index('history_id');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('received_date');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_trx_login_bonus_history');
    }
};
