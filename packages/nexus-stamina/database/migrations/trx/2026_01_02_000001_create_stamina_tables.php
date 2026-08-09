<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NexusPitr\Migrations\DynamicShardingTrait;

/**
 * TrxDB用スタミナテーブル作成マイグレーション
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
        // trx_stamina: プレイヤースタミナ管理
        // ========================================
        Schema::connection($connection)->create('trx_stamina', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('type', 50)->default('normal')->comment('スタミナタイプ（normal, raid, pvp, event等）');
            $table->unsignedInteger('current_stamina')->default(0)->comment('現在のスタミナ');
            $table->decimal('recovery_rate_multiplier', 5, 2)->default(1.00)->comment('回復速度倍率（VIP特典等）');
            $table->dateTime('last_recovery_at')->comment('最後の回復計算時刻');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['sys_player_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 各シャードに対してテーブルを削除（動的シャーディング対応）
        foreach ($this->getTrxConnections() as $connection) {
            Schema::connection($connection)->dropIfExists('trx_stamina');
        }
    }
};
