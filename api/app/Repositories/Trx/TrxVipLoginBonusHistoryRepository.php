<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxVipLoginBonusHistory;

/**
 * VIPログインボーナスの最新の受け取り状態（trx_vip_login_bonus）を扱うRepository。
 *
 * 受け取りの履歴は LogVipLoginBonusReceiveRepository が log に残す。
 *
 * @extends _BaseTrxRepository<TrxVipLoginBonusHistory>
 */
class TrxVipLoginBonusHistoryRepository extends _BaseTrxRepository implements VipLoginBonusHistoryRepositoryInterface
{
    /**
     * プレイヤーごとに1行を上書きするため is_delete カラムを持たない
     */
    protected bool $excludesSoftDeleted = false;

    protected string $modelClass = TrxVipLoginBonusHistory::class;

    /**
     * {@inheritDoc}
     *
     * キューに積むだけ。書き込みはQueryManager
     */
    public function upsertLatest(
        int $sysPlayerId,
        string $mstVipLoginBonusId,
        int $day,
        int $vipLevel,
        string $receivedAt,
    ): TrxVipLoginBonusHistory {
        $trxVipLoginBonus = $this->selectLatestByPlayerId($sysPlayerId);

        if ($trxVipLoginBonus === null) {
            $trxVipLoginBonus = new TrxVipLoginBonusHistory(['sys_player_id' => $sysPlayerId]);
            $trxVipLoginBonus->exists = false;
        }

        $trxVipLoginBonus->setMstVipLoginBonusId($mstVipLoginBonusId);
        $trxVipLoginBonus->setDay($day);
        $trxVipLoginBonus->setVipLevel($vipLevel);
        $trxVipLoginBonus->setReceivedAt($receivedAt);

        $this->setModel($trxVipLoginBonus);

        return $trxVipLoginBonus;
    }

    /**
     * {@inheritDoc}
     */
    public function selectLatestByPlayerId(int $sysPlayerId): ?TrxVipLoginBonusHistory
    {
        /** @var TrxVipLoginBonusHistory|null */
        return $this->queryOrMemory()->firstWhere('sys_player_id', $sysPlayerId);
    }

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerAndBonusAndDate(
        int $sysPlayerId,
        string $mstVipLoginBonusId,
        string $receivedAt,
    ): ?TrxVipLoginBonusHistory {
        $trxVipLoginBonus = $this->selectLatestByPlayerId($sysPlayerId);

        if ($trxVipLoginBonus === null
            || $trxVipLoginBonus->getMstVipLoginBonusId() !== $mstVipLoginBonusId
            || $trxVipLoginBonus->getReceivedAt() !== $receivedAt) {
            return null;
        }

        return $trxVipLoginBonus;
    }
}
