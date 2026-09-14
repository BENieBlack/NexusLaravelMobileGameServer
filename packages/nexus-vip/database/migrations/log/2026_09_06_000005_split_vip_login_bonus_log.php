<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('log_action_vip_login_bonus_receive')) {
            return;
        }

        if (Schema::hasTable('log_trx_vip_login_bonus_history')) {
            Schema::rename('log_trx_vip_login_bonus_history', 'log_action_vip_login_bonus_receive_legacy');
        }

        Schema::create('log_action_vip_login_bonus_receive', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sys_player_id');
            $table->string('mst_vip_login_bonus_id', 64);
            $table->unsignedInteger('day');
            $table->unsignedTinyInteger('vip_level');
            $table->dateTime('received_at');
            $table->string('reward_type');
            $table->string('reward_mst_id');
            $table->unsignedInteger('reward_amount');
            $table->boolean('is_paid')->default(false);
            $table->dateTime('created_at');
            $table->index('sys_player_id');
            $table->index('mst_vip_login_bonus_id');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_action_vip_login_bonus_receive');
    }
};
