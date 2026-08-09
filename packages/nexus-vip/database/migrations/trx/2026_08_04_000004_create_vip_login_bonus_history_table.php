<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * VIPログインボーナス受け取り履歴テーブル
     * シャーディング対応（trx1, trx2...）
     */
    public function up(): void
    {
        Schema::create('trx_vip_login_bonus_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->string('mst_vip_login_bonus_id', 64)->comment('VIPログインボーナスID');
            $table->unsignedInteger('day')->comment('受け取った日数（1日目、2日目...）');
            $table->unsignedTinyInteger('vip_level')->comment('受け取り時のVIPレベル');
            $table->timestamp('received_at')->comment('受け取り日時（UTC）');
            $table->timestamps();

            // sys_player_idでの検索用インデックス
            $table->index('sys_player_id');
            // VIPログインボーナスID + プレイヤーIDでの検索用
            $table->index(['mst_vip_login_bonus_id', 'sys_player_id'], 'idx_vip_bonus_player');
            // 受け取り日時での検索用
            $table->index('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_vip_login_bonus_history');
    }
};
