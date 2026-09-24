<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlayerHistoryController extends Controller
{
    private const LOG_TABLES = [
        'action_api_access' => 'log_action_api_access',
        'action_player_update' => 'log_action_player_update',
        'action_item_change' => 'log_action_item_change',
        'action_gacha_draw' => 'log_action_gacha_draw',
        'action_unit_levelup' => 'log_action_unit_levelup',
        'action_equipment_levelup' => 'log_action_equipment_levelup',
        'action_in_app_purchase' => 'log_action_in_app_purchase',
        'change_player' => 'log_change_trx_player',
        'change_player_sns' => 'log_change_trx_player_sns',
        'change_item' => 'log_change_trx_item',
        'change_unit' => 'log_change_trx_unit',
        'change_equipment' => 'log_change_trx_equipment',
        'change_gacha' => 'log_change_trx_gacha',
        'change_mailbox' => 'log_change_trx_mailbox',
        'change_wallet' => 'log_change_trx_wallet',
        'change_wallet_balance' => 'log_change_trx_wallet_balance',
        'change_stamina' => 'log_change_trx_stamina',
        'change_in_app_purchase' => 'log_change_trx_in_app_purchase',
        'change_in_app_purchase_effect' => 'log_change_trx_in_app_purchase_effect',
        'change_diamond' => 'log_change_trx_diamond',
        'change_diamond_balance' => 'log_change_trx_diamond_balance',
        'change_vip_point' => 'log_action_vip_point_change',
        'change_vip_login_bonus' => 'log_action_vip_login_bonus_receive',
        'change_login_bonus' => 'log_action_login_bonus_receive',
    ];

    public function index(Request $request): View
    {
        $playerId = (int) $request->input('player_id');
        $activeTab = (string) $request->input('tab', array_key_first(self::LOG_TABLES));
        $player = $playerId > 0
            ? DB::connection('sys')->table('sys_player')->where('id', $playerId)->first()
            : null;
        $records = [];

        if ($player && isset(self::LOG_TABLES[$activeTab])) {
            $table = self::LOG_TABLES[$activeTab];
            foreach ($this->logConnections() as $connection) {
                if (! DB::connection($connection)->getSchemaBuilder()->hasTable($table)) {
                    continue;
                }

                $records[$connection] = DB::connection($connection)
                    ->table($table)
                    ->where('sys_player_id', $player->id)
                    ->orderByDesc('created_at')
                    ->limit(100)
                    ->get();
            }
        }

        return view('players.history', [
            'player' => $player,
            'activeTab' => $activeTab,
            'tabs' => self::LOG_TABLES,
            'records' => $records,
        ]);
    }

    private function logConnections(): array
    {
        $count = max(1, (int) env('DB_SHARD_COUNT', 2));

        return array_merge(
            ['log'],
            array_map(static fn (int $number): string => "log{$number}", range(2, $count)),
        );
    }
}
