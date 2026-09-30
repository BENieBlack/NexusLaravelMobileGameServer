<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxVipLoginBonusHistory;
use Illuminate\Support\Facades\DB;
use NexusPitr\Logger\ShardMapper;

/**
 * VIPログインボーナスの最新状態をtrxへ、受取履歴をlogへ保存するRepository。
 */
class TrxVipLoginBonusHistoryRepository extends _BaseTrxRepository implements VipLoginBonusHistoryRepositoryInterface
{
    protected bool $excludesSoftDeleted = false;

    protected string $modelClass = TrxVipLoginBonusHistory::class;

    public function insert(array $data, string $connectionName): array
    {
        $state = [
            'sys_player_id' => $data['sys_player_id'],
            'mst_vip_login_bonus_id' => $data['mst_vip_login_bonus_id'],
            'day' => $data['day'],
            'vip_level' => $data['vip_level'],
            'received_at' => $data['received_at'],
            'updated_at' => now(),
        ];

        DB::connection($connectionName)->table('trx_vip_login_bonus')->updateOrInsert(
            ['sys_player_id' => $state['sys_player_id']],
            $state + ['created_at' => now()],
        );

        foreach ($data['rewards'] ?? [] as $reward) {
            DB::connection(ShardMapper::resolveLogConnection($connectionName))
                ->table('log_action_vip_login_bonus_receive')
                ->insert([
                    'sys_player_id' => $state['sys_player_id'],
                    'mst_vip_login_bonus_id' => $state['mst_vip_login_bonus_id'],
                    'day' => $state['day'],
                    'vip_level' => $state['vip_level'],
                    'received_at' => $state['received_at'],
                    'reward_type' => $reward['type'],
                    'reward_mst_id' => $reward['id'],
                    'reward_amount' => $reward['amount'],
                    'is_paid' => $reward['is_paid'] ?? false,
                    'created_at' => now(),
                ]);
        }

        return $state;
    }

    public function selectLatestByPlayerId(int $sysPlayerId, string $connectionName): ?array
    {
        $model = TrxVipLoginBonusHistory::on($connectionName)
            ->where('sys_player_id', $sysPlayerId)
            ->first();

        return $model?->toArray();
    }

    public function selectByPlayerAndBonusAndDate(
        int $sysPlayerId,
        string $vipLoginBonusId,
        string $receivedDate,
        string $connectionName
    ): ?array {
        $model = TrxVipLoginBonusHistory::on($connectionName)
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_vip_login_bonus_id', $vipLoginBonusId)
            ->where('received_at', $receivedDate)
            ->first();

        return $model?->toArray();
    }
}
