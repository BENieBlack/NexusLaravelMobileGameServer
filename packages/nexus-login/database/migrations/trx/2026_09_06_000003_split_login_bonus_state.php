<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trx_login_bonus_history') || Schema::hasTable('trx_login_bonus')) {
            return;
        }

        Schema::create('trx_login_bonus', function (Blueprint $table): void {
            $table->unsignedBigInteger('sys_player_id');
            $table->enum('type', ['daily', 'comeback']);
            $table->string('mst_login_bonus_id');
            $table->unsignedInteger('day')->default(0);
            $table->unsignedInteger('absent_days')->nullable();
            $table->dateTime('received_date');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->primary(['sys_player_id', 'type'], 'pk_login_bonus');
        });

        DB::table('trx_login_bonus')->insertUsing(
            ['sys_player_id', 'type', 'mst_login_bonus_id', 'day', 'absent_days', 'received_date', 'created_at', 'updated_at'],
            DB::table('trx_login_bonus_history AS history')
                ->joinSub(
                    DB::table('trx_login_bonus_history')
                        ->select('sys_player_id', DB::raw('MAX(id) AS id'))
                        ->groupBy('sys_player_id'),
                    'latest',
                    'latest.id',
                    '=',
                    'history.id'
                )
                ->selectRaw("history.sys_player_id, CASE WHEN history.mst_login_bonus_id LIKE 'comeback%' THEN 'comeback' ELSE 'daily' END, history.mst_login_bonus_id, history.day, history.absent_days, history.received_date, history.created_at, history.updated_at")
        );

        Schema::dropIfExists('trx_login_bonus_history');
    }

    public function down(): void
    {
        // 旧履歴テーブルへ戻す場合は履歴の再構成が必要なため自動復元しない。
    }
};
