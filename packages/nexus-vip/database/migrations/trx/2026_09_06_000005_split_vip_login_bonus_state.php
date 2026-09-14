<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trx_vip_login_bonus_history') || Schema::hasTable('trx_vip_login_bonus')) {
            return;
        }

        Schema::create('trx_vip_login_bonus', function (Blueprint $table): void {
            $table->unsignedBigInteger('sys_player_id')->primary();
            $table->string('mst_vip_login_bonus_id', 64);
            $table->unsignedInteger('day');
            $table->unsignedTinyInteger('vip_level');
            $table->timestamp('received_at');
            $table->timestamps();
        });

        DB::table('trx_vip_login_bonus')->insertUsing(
            ['sys_player_id', 'mst_vip_login_bonus_id', 'day', 'vip_level', 'received_at', 'created_at', 'updated_at'],
            DB::table('trx_vip_login_bonus_history AS history')
                ->joinSub(
                    DB::table('trx_vip_login_bonus_history')
                        ->select('sys_player_id', DB::raw('MAX(id) AS id'))
                        ->groupBy('sys_player_id'),
                    'latest',
                    'latest.id',
                    '=',
                    'history.id'
                )
                ->select([
                    'history.sys_player_id',
                    'history.mst_vip_login_bonus_id',
                    'history.day',
                    'history.vip_level',
                    'history.received_at',
                    'history.created_at',
                    'history.updated_at',
                ])
        );

        Schema::dropIfExists('trx_vip_login_bonus_history');
    }

    public function down(): void
    {
        // 旧履歴テーブルへ戻す場合は履歴の再構成が必要なため自動復元しない。
    }
};
