<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxVipLoginBonusHistory;

class TrxVipLoginBonusHistoryRepository extends _BaseTrxRepository implements VipLoginBonusHistoryRepositoryInterface
{
    protected string $modelClass = TrxVipLoginBonusHistory::class;

    /**
     * {@inheritDoc}
     */
    public function create(array $data, string $connectionName): array
    {
        $model = new TrxVipLoginBonusHistory($data);
        $model->setConnection($connectionName);
        $model->save();

        return $model->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function findLatestByPlayerId(int $sysPlayerId, string $connectionName): ?array
    {
        $model = TrxVipLoginBonusHistory::on($connectionName)
            ->where('sys_player_id', $sysPlayerId)
            ->orderBy('received_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $model?->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function findByPlayerAndBonusAndDate(
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
