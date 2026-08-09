<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NexusPitr\Migrations\DynamicShardingTrait;

/**
 * TrxDB用ウォレットテーブル作成マイグレーション
 * 
 * 注意: このマイグレーションは`php artisan trx:migrate`で実行してください。
 * TrxMigrateCommandが全TrxDBシャード（trx1, trx2, ...）に対して自動的に実行します。
 */
return new class extends Migration
{
    use DynamicShardingTrait;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 各シャードに対してテーブルを作成（動的シャーディング対応）
        foreach ($this->getTrxConnections() as $connection) {
            $this->createTablesForConnection($connection);
        }
    }

    /**
     * 指定された接続に対してテーブルを作成
     */
    protected function createTablesForConnection(string $connection): void
    {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 各シャードに対してテーブルを削除（動的シャーディング対応）
        foreach ($this->getTrxConnections() as $connection) {
            Schema::connection($connection)->dropIfExists('trx_wallet_balance');
            Schema::connection($connection)->dropIfExists('trx_wallet');
            Schema::connection($connection)->dropIfExists('trx_diamond_balance');
            Schema::connection($connection)->dropIfExists('trx_diamond');
        }
    }
};
