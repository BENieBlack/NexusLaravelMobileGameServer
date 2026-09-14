<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $renames = [
            'log_access' => 'log_action_api_access',
            'log_player' => 'log_action_player_update',
            'log_item' => 'log_action_item_change',
            'log_gacha' => 'log_action_gacha_draw',
            'log_unit' => 'log_action_unit_levelup',
            'log_equipment' => 'log_action_equipment_levelup',
            'log_in_app_purchase' => 'log_action_in_app_purchase',
            'log_login_bonus' => 'log_action_login_bonus_receive',
            'log_vip_login_bonus' => 'log_action_vip_login_bonus_receive',
            'log_vip_point' => 'log_action_vip_point_change',
            'log_login_bonus_legacy' => 'log_action_login_bonus_receive_legacy',
            'log_vip_login_bonus_legacy' => 'log_action_vip_login_bonus_receive_legacy',
            'log_trx_player' => 'log_change_player',
            'log_trx_player_sns' => 'log_change_player_sns',
            'log_trx_item' => 'log_change_item',
            'log_trx_unit' => 'log_change_unit',
            'log_trx_equipment' => 'log_change_equipment',
            'log_trx_stamina' => 'log_change_stamina',
            'log_trx_mailbox' => 'log_change_mailbox',
            'log_trx_wallet' => 'log_change_wallet',
            'log_trx_wallet_balance' => 'log_change_wallet_balance',
            'log_trx_in_app_purchase' => 'log_change_in_app_purchase',
            'log_trx_in_app_purchase_effect' => 'log_change_in_app_purchase_effect',
            'log_trx_diamond' => 'log_change_diamond',
            'log_trx_diamond_balance' => 'log_change_diamond_balance',
            'log_trx_gacha' => 'log_change_gacha',
            'log_trx_change' => 'log_change',
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
