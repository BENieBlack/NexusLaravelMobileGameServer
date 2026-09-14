<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $renames = [
            'log_action_access' => 'log_action_api_access',
            'log_action_player' => 'log_action_player_update',
            'log_action_item' => 'log_action_item_change',
            'log_action_gacha' => 'log_action_gacha_draw',
            'log_action_unit' => 'log_action_unit_levelup',
            'log_action_equipment' => 'log_action_equipment_levelup',
            'log_action_unit_change' => 'log_action_unit_levelup',
            'log_action_equipment_change' => 'log_action_equipment_levelup',
            'log_action_login_bonus' => 'log_action_login_bonus_receive',
            'log_action_vip_login_bonus' => 'log_action_vip_login_bonus_receive',
            'log_action_vip_point' => 'log_action_vip_point_change',
            'log_action_login_bonus_legacy' => 'log_action_login_bonus_receive_legacy',
            'log_action_vip_login_bonus_legacy' => 'log_action_vip_login_bonus_receive_legacy',
        ];

        foreach ($renames as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }
    }

    public function down(): void
    {
        // Renames are intentionally not reversed automatically.
    }
};
