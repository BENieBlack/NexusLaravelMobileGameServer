<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LogDB用スタミナログテーブル作成マイグレーション
 * 
 * 注意: このマイグレーションは`php artisan pitr:migrate`で実行してください。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // log_trx_stamina: スタミナ変更ログ
        // ========================================
        Schema::create('log_trx_stamina', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->string('stamina_type', 50)->comment('スタミナタイプ (normal, raid, pvp, event)');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');
            
            $table->json('before_data')->nullable()->comment('変更前データ');
            $table->json('after_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');
            
            $table->integer('stamina_diff')->nullable()->comment('スタミナ増減量');
            $table->unsignedInteger('before_stamina')->nullable()->comment('変更前スタミナ');
            $table->unsignedInteger('after_stamina')->nullable()->comment('変更後スタミナ');
            
            $table->string('reason', 100)->nullable()->comment('変更理由 (consume_quest, natural_recovery, item_recovery, vip_bonus, level_up_recovery, admin_grant)');
            $table->json('metadata')->nullable()->comment('メタデータ');
            
            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            
            $table->index('sys_player_id');
            $table->index('stamina_type');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('created_at');
            $table->index(['sys_player_id', 'stamina_type', 'created_at'], 'idx_player_type_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_trx_stamina');
    }
};
