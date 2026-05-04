<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * シャーディング対象の接続名
     */
    protected $connections = ['trx1', 'trx2'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 各シャードに対してテーブルを作成
        foreach ($this->connections as $connection) {
            $this->createTablesForConnection($connection);
        }
    }

    /**
     * 指定された接続に対してテーブルを作成
     */
    protected function createTablesForConnection(string $connection): void
    {
        // ========================================
        // trx_player: プレイヤートランザクションデータ
        // ========================================
        Schema::connection($connection)->create('trx_player', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->primary()->comment('sys_playerテーブルのID');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');
        });

        // ========================================
        // trx_player_sns: SNSアカウント連携情報
        // ========================================
        Schema::connection($connection)->create('trx_player_sns', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->enum('sns_type', ['apple', 'google', 'x', 'facebook'])->comment('SNSタイプ');
            $table->string('sns_user_id')->comment('SNSユーザーID');
            $table->string('auth')->comment('認証情報');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['sys_player_id', 'sns_type']);
        });

        // ========================================
        // trx_unit: プレイヤー所持ユニット
        // ========================================
        Schema::connection($connection)->create('trx_unit', function (Blueprint $table) {
            $table->id()->comment('ユニット所持ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_unit_id')->comment('マスターユニットID');
            $table->unsignedInteger('grade')->comment('グレード');
            $table->unsignedInteger('level')->comment('レベル');
            $table->unsignedBigInteger('level_exp')->default(0)->comment('現在のレベルの経験値');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index(['sys_player_id', 'mst_unit_id']);
        });

        // ========================================
        // trx_equipment: プレイヤー所持装備
        // ========================================
        Schema::connection($connection)->create('trx_equipment', function (Blueprint $table) {
            $table->id()->comment('装備所持ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_equipment_id')->comment('マスター装備ID');
            $table->unsignedInteger('grade')->comment('グレード');
            $table->unsignedInteger('level')->comment('レベル');
            $table->unsignedBigInteger('level_exp')->default(0)->comment('レベル経験値');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index(['sys_player_id', 'mst_equipment_id']);
        });

        // ========================================
        // trx_item: プレイヤー所持アイテム
        // ========================================
        Schema::connection($connection)->create('trx_item', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_item_id')->comment('マスターアイテムID');
            $table->unsignedInteger('amount')->comment('所持数');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['sys_player_id', 'mst_item_id']);
        });

        // ========================================
        // trx_in_app_purchase: 課金購入履歴管理
        // ========================================
        Schema::connection($connection)->create('trx_in_app_purchase', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('billing_platform')->comment('決済プラットフォーム (AppStore, GooglePlay, PayPal, Stripe等)');
            $table->string('mst_in_app_purchase_id')->comment('課金商品マスターID');
            $table->unsignedInteger('total_purchase_count')->default(0)->comment('累計購入回数');
            $table->unsignedInteger('purchase_count')->default(0)->comment('期間内購入回数（リセット可能）');
            $table->dateTime('purchase_count_reset_at')->nullable()->comment('購入回数リセット日時');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['sys_player_id', 'billing_platform', 'mst_in_app_purchase_id']);
        });

        // ========================================
        // trx_in_app_purchase_effect: Pass課金効果管理
        // ========================================
        Schema::connection($connection)->create('trx_in_app_purchase_effect', function (Blueprint $table) {
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
        Schema::connection($connection)->create('trx_diamond', function (Blueprint $table) {
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
        Schema::connection($connection)->create('trx_diamond_balance', function (Blueprint $table) {
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

        // ========================================
        // trx_wallet: 汎用通貨現在値管理
        // Gold, EventCoin, RaidMedal等を統合管理
        // ========================================
        Schema::connection($connection)->create('trx_wallet', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_item_id')->comment('通貨アイテムID (例: gold, event_coin)');
            $table->unsignedInteger('amount')->default(0)->comment('現在の残高');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['sys_player_id', 'mst_item_id']);
        });

        // ========================================
        // trx_wallet_balance: 通貨残高管理（取得単位）
        // FIFO方式で消費し、有効期限管理を可能にする
        // ========================================
        Schema::connection($connection)->create('trx_wallet_balance', function (Blueprint $table) {
            $table->id()->comment('残高ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_item_id')->comment('通貨アイテムID (例: gold, event_coin)');
            $table->unsignedInteger('current_amount')->comment('現在の残数');
            $table->unsignedInteger('initial_amount')->comment('取得時の数');
            $table->dateTime('expire_at')->nullable()->comment('有効期限 (NULLの場合は無期限)');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index(['sys_player_id', 'mst_item_id', 'expire_at', 'id']);
        });

        // ========================================
        // trx_stamina: プレイヤースタミナ管理
        // ========================================
        Schema::connection($connection)->create('trx_stamina', function (Blueprint $table) {
            $table->id()->comment('スタミナID');
            $table->unsignedBigInteger('sys_player_id')->unique()->comment('sys_playerテーブルのID');
            $table->unsignedInteger('current_stamina')->default(0)->comment('現在のスタミナ');
            $table->decimal('recovery_rate_multiplier', 5, 2)->default(1.00)->comment('回復速度倍率（VIP特典等）');
            $table->dateTime('last_recovery_at')->comment('最後の回復計算時刻');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('sys_player_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 各シャードに対してテーブルを削除
        foreach ($this->connections as $connection) {
            Schema::connection($connection)->dropIfExists('trx_stamina');
            Schema::connection($connection)->dropIfExists('trx_wallet_balance');
            Schema::connection($connection)->dropIfExists('trx_wallet');
            Schema::connection($connection)->dropIfExists('trx_diamond_balance');
            Schema::connection($connection)->dropIfExists('trx_diamond');
            Schema::connection($connection)->dropIfExists('trx_in_app_purchase_effect');
            Schema::connection($connection)->dropIfExists('trx_in_app_purchase');
            Schema::connection($connection)->dropIfExists('trx_item');
            Schema::connection($connection)->dropIfExists('trx_equipment');
            Schema::connection($connection)->dropIfExists('trx_unit');
            Schema::connection($connection)->dropIfExists('trx_player_sns');
            Schema::connection($connection)->dropIfExists('trx_player');
        }
    }
};
