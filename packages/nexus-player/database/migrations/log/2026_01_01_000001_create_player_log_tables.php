<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LogDB用プレイヤーログテーブル作成マイグレーション
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
        // ========================================
        // log_trx_player: プレイヤーアカウント変更ログ
        // ========================================
        Schema::create('log_trx_player', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');
            
            // State Data
            $table->json('old_data')->nullable()->comment('変更前データ (is_delete)');
            $table->json('new_data')->nullable()->comment('変更後データ (is_delete)');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');
            
            // Business Context
            $table->string('reason', 100)->nullable()->comment('変更理由 (account_creation, reactivation, soft_delete, data_migration)');
            $table->json('metadata')->nullable()->comment('メタデータ (例: {"device_id": "...", "platform": "iOS", "app_version": "1.2.3"})');
            
            // Audit Trail
            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID (log_accessと結合)');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID (CS操作の場合)');
            
            $table->dateTime('created_at')->useCurrent()->comment('作成日時');
            
            // Indexes
            $table->index('sys_player_id');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('created_at');
            $table->index(['sys_player_id', 'created_at'], 'idx_player_created');
        });

        // ========================================
        // log_trx_player_sns: SNS連携変更ログ
        // ========================================
        Schema::create('log_trx_player_sns', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');
            
            // State Data
            $table->json('old_data')->nullable()->comment('変更前データ (sns_type, sns_user_id, is_delete)');
            $table->json('new_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');
            
            // Business Context
            $table->enum('sns_type', ['apple', 'google', 'x', 'facebook'])->nullable()->comment('SNSタイプ');
            $table->string('reason', 100)->nullable()->comment('変更理由 (link, unlink, relink, migration)');
            $table->json('metadata')->nullable()->comment('メタデータ (例: {"device_id": "...", "ip_address": "...", "is_first_link": true})');
            
            // Security Audit
            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID (CS操作の場合)');
            
            $table->dateTime('created_at')->useCurrent()->comment('作成日時');
            
            // Indexes
            $table->index('sys_player_id');
            $table->index('sns_type');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('created_at');
            $table->index(['sys_player_id', 'sns_type', 'created_at'], 'idx_player_sns_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_trx_player_sns');
        Schema::dropIfExists('log_trx_player');
    }
};
