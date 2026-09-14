<?php

namespace App\Repositories\Trx;

use Illuminate\Support\Facades\DB;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;
use NexusPitr\Logger\ShardMapper;

/**
 * ログインボーナスの最新状態をtrxへ、受取履歴をlogへ保存するRepository。
 */
class LoginBonusHistoryRepositoryAdapter implements LoginBonusHistoryRepositoryInterface
{
    public function selectLatestByPlayer(int $sysPlayerId, string $connectionName): ?array
    {
        $result = DB::connection($connectionName)
            ->table('trx_login_bonus')
            ->where('sys_player_id', $sysPlayerId)
            ->where('type', 'daily')
            ->first();

        return $result ? (array) $result : null;
    }

    public function selectLatestByPlayerId(int $sysPlayerId, string $connectionName): ?array
    {
        return $this->selectLatestByPlayer($sysPlayerId, $connectionName);
    }

    public function countUniqueDaysSince(int $sysPlayerId, string $sinceDate, string $connectionName): int
    {
        return DB::connection($connectionName)
            ->table('trx_login_bonus')
            ->where('sys_player_id', $sysPlayerId)
            ->where('type', 'daily')
            ->where('received_date', '>=', $sinceDate)
            ->count();
    }

    public function insert(array $data, string $connectionName): void
    {
        $state = [
            'sys_player_id' => $data['sys_player_id'],
            'type' => $data['type'],
            'mst_login_bonus_id' => $data['mst_login_bonus_id'],
            'day' => $data['day'] ?? 0,
            'absent_days' => $data['absent_days'] ?? null,
            'received_date' => $data['received_date'],
            'updated_at' => now(),
        ];

        DB::connection($connectionName)->table('trx_login_bonus')->updateOrInsert(
            ['sys_player_id' => $state['sys_player_id'], 'type' => $state['type']],
            $state + ['created_at' => now()],
        );

        DB::connection(ShardMapper::resolveLogConnection($connectionName))
            ->table('log_action_login_bonus_receive')
            ->insert([
                'sys_player_id' => $data['sys_player_id'],
                'type' => $data['type'],
                'mst_login_bonus_id' => $data['mst_login_bonus_id'],
                'day' => $data['day'] ?? 0,
                'absent_days' => $data['absent_days'] ?? null,
                'received_at' => $data['received_date'],
                'reward_type' => $data['reward_type'],
                'reward_mst_id' => $data['reward_mst_id'],
                'reward_amount' => $data['reward_amount'],
                'is_paid' => $data['is_paid'] ?? false,
                'created_at' => now(),
            ]);
    }

    public function selectFirstComebackByPlayerId(int $sysPlayerId, string $connectionName): ?array
    {
        $result = DB::connection($connectionName)
            ->table('trx_login_bonus')
            ->where('sys_player_id', $sysPlayerId)
            ->where('type', 'comeback')
            ->first();

        return $result ? (array) $result : null;
    }

    public function selectByPlayerAndBonusAndDate(
        int $sysPlayerId,
        string $bonusId,
        string $receivedDate,
        string $connectionName
    ): ?array {
        $result = DB::connection($connectionName)
            ->table('trx_login_bonus')
            ->where('sys_player_id', $sysPlayerId)
            ->where('type', 'comeback')
            ->where('mst_login_bonus_id', $bonusId)
            ->where('received_date', $receivedDate)
            ->first();

        return $result ? (array) $result : null;
    }
}
