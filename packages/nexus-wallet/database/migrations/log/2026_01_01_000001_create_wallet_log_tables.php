<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LogDB用ウォレットログテーブル作成マイグレーション
 * 
 * 注意: このマイグレーションは`php artisan pitr:migrate`で実行してください。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // log_trx_wallet: 通貨変更ログ
        // ========================================
        Schema::create('log_trx_wallet', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->string('mst_item_id')->comment('通貨アイテムID');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');
            
            $table->json('old_data')->nullable()->comment('変更前データ');
            $table->json('new_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');
            
            $table->integer('free_amount_diff')->nullable()->comment('無償通貨増減量');
            $table->integer('paid_amount_diff')->nullable()->comment('有償通貨増減量');
            $table->unsignedInteger('before_free_amount')->nullable()->comment('変更前無償残高');
            $table->unsignedInteger('after_free_amount')->nullable()->comment('変更後無償残高');
            $table->unsignedInteger('before_paid_amount')->nullable()->comment('変更前有償残高');
            $table->unsignedInteger('after_paid_amount')->nullable()->comment('変更後有償残高');
            
            $table->string('reason', 100)->nullable()->comment('変更理由 (quest_reward, purchase, consume_shop, consume_gacha, daily_login, event_reward, admin_grant)');
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
            $table->index(['sys_player_id', 'mst_item_id', 'created_at'], 'idx_player_currency_created');
        });

        // ========================================
        // log_trx_wallet_balance: 通貨残高変更ログ
        // ========================================
        Schema::create('log_trx_wallet_balance', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->unsignedBigInteger('balance_id')->nullable()->comment('残高ID');
            $table->string('mst_item_id')->comment('通貨アイテムID');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');
            
            $table->json('old_data')->nullable()->comment('変更前データ');
            $table->json('new_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');
            
            $table->integer('amount_diff')->nullable()->comment('残高増減量');
            $table->unsignedInteger('before_current_amount')->nullable()->comment('変更前残高');
            $table->unsignedInteger('after_current_amount')->nullable()->comment('変更後残高');
            
            $table->boolean('is_paid')->nullable()->comment('有償フラグ');
            $table->dateTime('expire_at')->nullable()->comment('有効期限');
            $table->string('reason', 100)->nullable()->comment('変更理由 (grant, consume, expire, admin_grant)');
            $table->json('metadata')->nullable()->comment('メタデータ');
            
            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID');
            $table->dateTime('created_at')->useCurrent()->comment('作成日時');
            
            $table->index('sys_player_id');
            $table->index('balance_id');
            $table->index('mst_item_id');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('expire_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_trx_wallet_balance');
        Schema::dropIfExists('log_trx_wallet');
    }
};
