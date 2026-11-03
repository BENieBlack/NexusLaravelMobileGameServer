<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxVipLoginBonus;

/**
 * VIPログインボーナスの最新の受け取り状態（trx_vip_login_bonus）を扱うRepository。
 *
 * 受け取りの履歴は LogVipLoginBonusReceiveRepository が log に残す。
 *
 * @extends _BaseTrxRepository<TrxVipLoginBonus>
 */
class TrxVipLoginBonusRepository extends _BaseTrxRepository implements VipLoginBonusHistoryRepositoryInterface
{
    /**
     * プレイヤーごとに1行を上書きするため is_delete カラムを持たない
     */
    protected bool $excludesSoftDeleted = false;

    protected string $modelClass = TrxVipLoginBonus::class;

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
    ): TrxVipLoginBonus {
        $trxVipLoginBonus = $this->selectLatestByPlayerId($sysPlayerId);

        if ($trxVipLoginBonus === null) {
            $trxVipLoginBonus = new TrxVipLoginBonus(['sys_player_id' => $sysPlayerId]);
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
    public function selectLatestByPlayerId(int $sysPlayerId): ?TrxVipLoginBonus
    {
        /** @var TrxVipLoginBonus|null */
        return $this->queryOrMemory()->firstWhere('sys_player_id', $sysPlayerId);
    }

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerAndBonusAndDate(
        int $sysPlayerId,
        string $mstVipLoginBonusId,
        string $receivedAt,
    ): ?TrxVipLoginBonus {
        $trxVipLoginBonus = $this->selectLatestByPlayerId($sysPlayerId);

        if ($trxVipLoginBonus === null
            || $trxVipLoginBonus->getMstVipLoginBonusId() !== $mstVipLoginBonusId
            || $trxVipLoginBonus->getReceivedAt() !== $receivedAt) {
            return null;
        }

        return $trxVipLoginBonus;
    }
}
