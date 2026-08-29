<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LogDB用メールボックスログテーブル作成マイグレーション
 * 
 * 注意: このマイグレーションは`php artisan pitr:migrate`で実行してください。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // log_trx_mailbox: メールボックス変更ログ
        // ========================================
        Schema::create('log_trx_mailbox', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->unsignedBigInteger('trx_mailbox_id')->nullable()->comment('trx_mailboxテーブルのID');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');
            
            $table->json('before_data')->nullable()->comment('変更前データ');
            $table->json('after_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');
            
            $table->string('mst_mailbox_id')->nullable()->comment('マスターメールボックスID');
            $table->boolean('is_opened')->nullable()->comment('既読フラグ');
            $table->boolean('is_received')->nullable()->comment('受取済みフラグ');
            $table->boolean('is_protected')->nullable()->comment('保護フラグ');
            $table->dateTime('expires_at')->nullable()->comment('有効期限');
            
            $table->string('reason', 100)->nullable()->comment('変更理由 (send, open, receive, delete, expire, protect, unprotect)');
            $table->json('metadata')->nullable()->comment('メタデータ');
            
            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            
            $table->index('sys_player_id');
            $table->index('trx_mailbox_id');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('created_at');
            $table->index(['sys_player_id', 'created_at'], 'idx_player_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_trx_mailbox');
    }
};
