<?php

use Illuminate\Database\Migrations\Migration;
use NexusPitr\Migrations\DynamicShardingTrait;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 接続先データベース（シャーディング対応）
     */
    protected $connections = $this->getTrxConnections();

    /**
     * Run the migrations.
     * 
     * カムバックログインボーナス履歴用にtrx_login_bonus_historyテーブルを拡張
     */
    public function up(): void
    {
        foreach ($this->getTrxConnections() as $connection) {
            Schema::connection($connection)->table('trx_login_bonus_history', function (Blueprint $table) {
                // カムバックボーナス用: 休眠日数
                $table->unsignedInteger('absent_days')
                      ->nullable()
                      ->comment('休眠日数（カムバックボーナスの場合のみ）')
                      ->after('mst_login_bonus_id');
                
                // インデックス追加
                $table->index('absent_days', 'idx_absent_days');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->getTrxConnections() as $connection) {
            Schema::connection($connection)->table('trx_login_bonus_history', function (Blueprint $table) {
                $table->dropIndex('idx_absent_days');
                $table->dropColumn('absent_days');
            });
        }
    }
};
