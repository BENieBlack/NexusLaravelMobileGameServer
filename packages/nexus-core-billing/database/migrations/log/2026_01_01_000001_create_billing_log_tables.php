<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LogDB用課金ログテーブル作成マイグレーション
 *
 * 注意: このマイグレーションは`php artisan pitr:migrate`で実行してください。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // log_trx_in_app_purchase: 課金購入履歴変更ログ
        // ========================================
        Schema::create('log_trx_in_app_purchase', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->string('billing_platform')->comment('決済プラットフォーム');
            $table->string('mst_in_app_purchase_id')->comment('課金商品マスターID');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');

            $table->json('before_data')->nullable()->comment('変更前データ');
            $table->json('after_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');

            $table->string('transaction_id', 255)->nullable()->comment('プラットフォームトランザクションID');
            $table->integer('purchase_count_diff')->nullable()->comment('購入回数増減');
            $table->unsignedInteger('total_purchase_count')->nullable()->comment('変更後累計購入回数');

            $table->string('reason', 100)->nullable()->comment('変更理由 (purchase, refund, reset_count, admin_adjustment)');
            $table->json('metadata')->nullable()->comment('メタデータ');

            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');

            $table->index('sys_player_id');
            $table->index('billing_platform');
            $table->index('mst_in_app_purchase_id');
            $table->index('transaction_id');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('created_at');
        });

        // ========================================
        // log_trx_in_app_purchase_effect: 課金効果変更ログ
        // ========================================
        Schema::create('log_trx_in_app_purchase_effect', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->unsignedBigInteger('trx_in_app_purchase_effect_id')->nullable()->comment('trx_in_app_purchase_effectテーブルのID');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');

            $table->json('before_data')->nullable()->comment('変更前データ');
            $table->json('after_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');

            $table->string('effect_type')->nullable()->comment('効果タイプ (idle_reward_multiplier, ad_skip, exp_boost, gold_boost, daily_mission_bonus)');
            $table->decimal('value', 10, 2)->nullable()->comment('効果値');
            $table->dateTime('expires_at')->nullable()->comment('有効期限');
            $table->boolean('is_active')->nullable()->comment('有効フラグ');

            $table->string('reason', 100)->nullable()->comment('変更理由 (purchase, expire, deactivate, admin_grant, admin_revoke)');
            $table->json('metadata')->nullable()->comment('メタデータ');

            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');

            $table->index('sys_player_id');
            // 自動生成名だと64文字を超えるため明示的に付ける
            $table->index('trx_in_app_purchase_effect_id', 'idx_effect_id');
            $table->index('effect_type');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('created_at');
        });

        // ========================================
        // log_trx_diamond: ダイヤモンド変更ログ
        // ========================================
        Schema::create('log_trx_diamond', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->string('platform')->comment('プラットフォーム (Apple, Google)');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');

            $table->json('before_data')->nullable()->comment('変更前データ');
            $table->json('after_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');

            $table->integer('paid_amount_diff')->nullable()->comment('有償ダイヤ増減量');
            $table->integer('free_amount_diff')->nullable()->comment('無償ダイヤ増減量');
            $table->unsignedInteger('before_paid_amount')->nullable()->comment('変更前有償ダイヤ');
            $table->unsignedInteger('after_paid_amount')->nullable()->comment('変更後有償ダイヤ');
            $table->unsignedInteger('before_free_amount')->nullable()->comment('変更前無償ダイヤ');
            $table->unsignedInteger('after_free_amount')->nullable()->comment('変更後無償ダイヤ');

            $table->string('reason', 100)->nullable()->comment('変更理由 (purchase, consume_gacha, consume_shop, refund, event_reward, admin_grant)');
            $table->json('metadata')->nullable()->comment('メタデータ');

            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');

            $table->index('sys_player_id');
            $table->index('platform');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('created_at');
            $table->index(['sys_player_id', 'platform', 'created_at'], 'idx_player_platform_created');
        });

        // ========================================
        // log_trx_diamond_balance: ダイヤモンド残高変更ログ
        // ========================================
        Schema::create('log_trx_diamond_balance', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->unsignedBigInteger('trx_diamond_balance_id')->nullable()->comment('trx_diamond_balanceテーブルのID');
            $table->string('platform')->comment('プラットフォーム');
            $table->enum('operation_type', ['insert', 'update', 'delete'])->comment('操作タイプ');

            $table->json('before_data')->nullable()->comment('変更前データ');
            $table->json('after_data')->nullable()->comment('変更後データ');
            $table->json('changed_columns')->nullable()->comment('変更カラムリスト');

            $table->integer('amount_diff')->nullable()->comment('残高増減量');
            $table->unsignedInteger('before_current_amount')->nullable()->comment('変更前残高');
            $table->unsignedInteger('after_current_amount')->nullable()->comment('変更後残高');
            $table->decimal('unit_price', 10, 2)->nullable()->comment('単価');

            $table->string('billing_platform')->nullable()->comment('決済プラットフォーム');
            $table->string('reason', 100)->nullable()->comment('変更理由 (purchase, consume, refund)');
            $table->json('metadata')->nullable()->comment('メタデータ');

            $table->string('unique_request_id', 100)->nullable()->comment('リクエストID');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->unsignedBigInteger('admin_id')->nullable()->comment('管理者ID');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');

            $table->index('sys_player_id');
            $table->index('trx_diamond_balance_id');
            $table->index('platform');
            $table->index('operation_type');
            $table->index('reason');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_trx_diamond_balance');
        Schema::dropIfExists('log_trx_diamond');
        Schema::dropIfExists('log_trx_in_app_purchase_effect');
        Schema::dropIfExists('log_trx_in_app_purchase');
    }
};
