<?php

use Illuminate\Database\Migrations\Migration;
use NexusPitr\Migrations\DynamicShardingTrait;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use DynamicShardingTrait;

    /**
     * Run the migrations.
     *
     * スタミナタイプを追加して複数種類のスタミナ管理を可能にする
     * 
     * PRIMARY KEY: (sys_player_id, type)に変更
     * 
     * typeの例:
     * - 'normal': 通常スタミナ（クエスト用）
     * - 'raid': レイドスタミナ
     * - 'pvp': PVPスタミナ
     * - 'event': イベント専用スタミナ
     */
    public function up(): void
    {
        foreach ($this->getTrxConnections() as $connection) {
            Schema::connection($connection)->table('trx_stamina', function (Blueprint $table) {
                // idカラムのAUTO_INCREMENTを削除してから主キーを削除
                $table->bigInteger('id')->change();
            });
            
            Schema::connection($connection)->table('trx_stamina', function (Blueprint $table) {
                $table->dropPrimary('PRIMARY');
                $table->dropColumn('id');
                
                // typeカラムを追加（sys_player_idの直後）
                $table->string('type', 50)->after('sys_player_id')->default('normal')
                    ->comment('スタミナタイプ（normal, raid, pvp, event等）');
                
                // 複合主キーを設定
                $table->primary(['sys_player_id', 'type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->getTrxConnections() as $connection) {
            Schema::connection($connection)->table('trx_stamina', function (Blueprint $table) {
                // 複合主キーを削除
                $table->dropPrimary(['sys_player_id', 'type']);
                
                // typeカラムを削除
                $table->dropColumn('type');
            });
            
            Schema::connection($connection)->table('trx_stamina', function (Blueprint $table) {
                // idカラムを再追加（AUTO_INCREMENT）
                $table->bigIncrements('id')->first();
            });
        }
    }
};
