<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxVipLoginBonusHistory;

class TrxVipLoginBonusHistoryRepository extends _BaseTrxRepository implements VipLoginBonusHistoryRepositoryInterface
{
    /**
     * trx_vip_login_bonus_history は履歴テーブルで is_delete を持たない
     */
    protected bool $excludesSoftDeleted = false;

    protected string $modelClass = TrxVipLoginBonusHistory::class;

    /**
     * {@inheritDoc}
     */
    public function insert(array $data, string $connectionName): array
    {
        // キューはRepositoryの接続先でまとめて実行されるため、
        // 対象シャードをRepository側にも反映する
        $this->setConnection($connectionName);

        $model = new TrxVipLoginBonusHistory($data);
        $model->setConnection($connectionName);
        $model->exists = false;
        $this->setModel($model);

        return $model->toArray();
    }

    /**
     * {@inheritDoc}
     */
    public function selectLatestByPlayerId(int $sysPlayerId, string $connectionName): ?array
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
