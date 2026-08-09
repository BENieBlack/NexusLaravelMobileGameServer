<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TrxDB用課金テーブル作成マイグレーション
 * 
 * 注意: このマイグレーションは`php artisan trx:migrate`で実行してください。
 * TrxMigrateCommandが全TrxDBシャード（trx1, trx2, ...）に対して自動的に実行します。
 */
return new class extends Migration
{

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ========================================
        // trx_in_app_purchase: 課金購入履歴管理
        // ========================================
        Schema::create('trx_in_app_purchase', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('billing_platform')->comment('決済プラットフォーム (AppStore, GooglePlay, PayPal, Stripe等)');
            $table->string('mst_in_app_purchase_id')->comment('課金商品マスターID');
            $table->string('transaction_id', 255)->nullable()->comment('プラットフォーム固有のトランザクションID（Apple: transaction_id, Google: orderId）');
            $table->unsignedInteger('total_purchase_count')->default(0)->comment('累計購入回数');
            $table->unsignedInteger('purchase_count')->default(0)->comment('期間内購入回数（リセット可能）');
            $table->dateTime('purchase_count_reset_at')->nullable()->comment('購入回数リセット日時');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['sys_player_id', 'billing_platform', 'mst_in_app_purchase_id']);
            $table->unique(['billing_platform', 'transaction_id'], 'unique_transaction_per_platform');
        });

        // ========================================
        // trx_in_app_purchase_effect: Pass課金効果管理
        // ========================================
        Schema::create('trx_in_app_purchase_effect', function (Blueprint $table) {
            $table->id()->comment('レコードID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->unsignedBigInteger('mst_in_app_purchase_id')->comment('課金商品マスターID');
            $table->enum('effect_type', ['IdleRewardMultiplier', 'AdSkip', 'ExpBoost', 'GoldBoost', 'DailyMissionBonus'])->comment('効果タイプ');
            $table->decimal('value', 10, 2)->comment('効果値');
            $table->dateTime('expires_at')->comment('効果の有効期限');
            $table->boolean('is_active')->default(true)->comment('有効フラグ（手動で無効化可能）');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index(['sys_player_id', 'effect_type', 'is_active', 'expires_at'], 'idx_active_effects');
            $table->index('expires_at');
        });

        // ========================================
        // trx_diamond: ダイヤモンド現在値管理
        // ========================================
        Schema::create('trx_diamond', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('platform')->comment('プラットフォーム (Apple, Google)');
            $table->unsignedInteger('paid_amount')->default(0)->comment('有償ダイヤモンド数');
            $table->unsignedInteger('free_amount')->default(0)->comment('無償ダイヤモンド数');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['sys_player_id', 'platform']);
        });

        // ========================================
        // trx_diamond_balance: ダイヤモンド残高管理（購入単位）
        // FIFO方式で消費し、返金計算を可能にする
        // ========================================
        Schema::create('trx_diamond_balance', function (Blueprint $table) {
            $table->id()->comment('残高ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('platform')->comment('プラットフォーム (Apple, Google)');
            $table->string('billing_platform')->comment('決済プラットフォーム (AppStore, GooglePlay, PayPal, Stripe等)');
            $table->unsignedInteger('current_amount')->comment('現在の残高');
            $table->unsignedInteger('purchase_amount')->comment('購入時の数量');
            $table->decimal('unit_price', 10, 2)->unsigned()->comment('単価（返金計算用）');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index(['sys_player_id', 'platform', 'billing_platform'], 'idx_diamond_balance_player_platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_diamond_balance');
        Schema::dropIfExists('trx_diamond');
        Schema::dropIfExists('trx_in_app_purchase_effect');
        Schema::dropIfExists('trx_in_app_purchase');
    }
};
