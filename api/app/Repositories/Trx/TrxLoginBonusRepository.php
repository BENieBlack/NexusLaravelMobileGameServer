<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxLoginBonus;

/**
 * TrxLoginBonusRepository
 *
 * ログインボーナスの最新の受け取り状態（trx_login_bonus）の永続化のみを担当
 * ビジネスロジックは App\Domain\Login\Services の各 LoginBonusService に実装
 *
 * @extends _BaseTrxRepository<TrxLoginBonus>
 */
class TrxLoginBonusRepository extends _BaseTrxRepository
{
    /**
     * プレイヤー×タイプごとに1行を上書きするため is_delete カラムを持たない
     */
    protected bool $excludesSoftDeleted = false;

    protected string $modelClass = TrxLoginBonus::class;

    /**
     * プレイヤーの指定タイプの受け取り状態を取得する（まだ受け取っていなければnull）
     *
     * @param  string  $type  daily / comeback
     */
    public function selectByType(int $sysPlayerId, string $type): ?TrxLoginBonus
    {
        /** @var TrxLoginBonus|null */
        return $this->queryOrMemory()
            ->first(fn (TrxLoginBonus $trxLoginBonus) => $trxLoginBonus->getSysPlayerId() === $sysPlayerId
                && $trxLoginBonus->getType() === $type);
    }

    /**
     * 受け取り状態を書き換える（無ければ作る）。キューに積むだけ。書き込みはQueryManager
     *
     * @param  string  $type  daily / comeback
     * @param  string  $receivedDate  受け取り日時（Y-m-d H:i:s形式）
     */
    public function upsertLatest(
        int $sysPlayerId,
        string $type,
        string $mstLoginBonusId,
        int $day,
        ?int $absentDays,
        string $receivedDate,
    ): TrxLoginBonus {
        $trxLoginBonus = $this->selectByType($sysPlayerId, $type);

        if ($trxLoginBonus === null) {
            $trxLoginBonus = new TrxLoginBonus(['sys_player_id' => $sysPlayerId, 'type' => $type]);
            $trxLoginBonus->exists = false;
        }

        $trxLoginBonus->setMstLoginBonusId($mstLoginBonusId);
        $trxLoginBonus->setDay($day);
        $trxLoginBonus->setAbsentDays($absentDays);
        $trxLoginBonus->setReceivedDate($receivedDate);

        $this->setModel($trxLoginBonus);

        return $trxLoginBonus;
    }
}
