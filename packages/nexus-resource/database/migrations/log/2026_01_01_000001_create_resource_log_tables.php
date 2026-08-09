<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LogDB用リソースログテーブル作成マイグレーション
 * 
 * 注意: このマイグレーションは`php artisan pitr:migrate`で実行してください。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // log_trx_unit: ユニット変更ログ
        // ========================================
        Schema::create('log_trx_unit', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->unsignedBigInteger('unit_id')->nullable()->comment('ユニット所持ID');
            $table->string('mst_unit_id')->nullable()->comment('マスターユニットID');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');
            
            $table->json('old_data')->nullable()->comment('変更前データ');
            $table->json('new_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');
            
            $table->string('reason', 100)->nullable()->comment('変更理由 (gacha, quest_reward, level_up, grade_up, exchange, present, delete)');
            $table->json('metadata')->nullable()->comment('メタデータ');
            
            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID');
            $table->dateTime('created_at')->useCurrent()->comment('作成日時');
            
            $table->index('sys_player_id');
            $table->index('unit_id');
            $table->index('mst_unit_id');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('created_at');
            $table->index(['sys_player_id', 'created_at'], 'idx_player_created');
        });

        // ========================================
        // log_trx_equipment: 装備変更ログ
        // ========================================
        Schema::create('log_trx_equipment', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->unsignedBigInteger('equipment_id')->nullable()->comment('装備所持ID');
            $table->string('mst_equipment_id')->nullable()->comment('マスター装備ID');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');
            
            $table->json('old_data')->nullable()->comment('変更前データ');
            $table->json('new_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');
            
            $table->string('reason', 100)->nullable()->comment('変更理由 (gacha, quest_reward, level_up, grade_up, craft, exchange, present, delete)');
            $table->json('metadata')->nullable()->comment('メタデータ');
            
            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID');
            $table->dateTime('created_at')->useCurrent()->comment('作成日時');
            
            $table->index('sys_player_id');
            $table->index('equipment_id');
            $table->index('mst_equipment_id');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('created_at');
            $table->index(['sys_player_id', 'created_at'], 'idx_player_created');
        });

        // ========================================
        // log_trx_item: アイテム変更ログ
        // ========================================
        Schema::create('log_trx_item', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->string('mst_item_id')->comment('マスターアイテムID');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');
            
            $table->json('old_data')->nullable()->comment('変更前データ');
            $table->json('new_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');
            
            $table->integer('free_amount_diff')->nullable()->comment('無償アイテム増減量');
            $table->integer('paid_amount_diff')->nullable()->comment('有償アイテム増減量');
            
            $table->string('reason', 100)->nullable()->comment('変更理由 (quest_reward, shop_purchase, consume_craft, consume_upgrade, gacha, present, expire)');
            $table->json('metadata')->nullable()->comment('メタデータ');
            
            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID');
            $table->dateTime('created_at')->useCurrent()->comment('作成日時');
            
            $table->index('sys_player_id');
            $table->index('mst_item_id');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('created_at');
            $table->index(['sys_player_id', 'mst_item_id', 'created_at'], 'idx_player_item_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_trx_item');
        Schema::dropIfExists('log_trx_equipment');
        Schema::dropIfExists('log_trx_unit');
    }
};
