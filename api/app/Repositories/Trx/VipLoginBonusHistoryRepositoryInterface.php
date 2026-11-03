<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxVipLoginBonusHistory;

/**
 * VIPログインボーナスの最新の受け取り状態（trx_vip_login_bonus）へのアクセス
 *
 * 対象はログイン中のプレイヤーのシャード（ApiSession が解決する）
 */
interface VipLoginBonusHistoryRepositoryInterface
{
    /**
     * 最新の受け取り状態を書き換える（無ければ作る）
     *
     * @param  string  $receivedAt  受け取り日時（Y-m-d H:i:s形式）
     */
    public function upsertLatest(
        int $sysPlayerId,
        string $mstVipLoginBonusId,
        int $day,
        int $vipLevel,
        string $receivedAt,
    ): TrxVipLoginBonusHistory;

    /**
     * プレイヤーの最新の受け取り状態を取得する（まだ受け取っていなければnull）
     */
    public function selectLatestByPlayerId(int $sysPlayerId): ?TrxVipLoginBonusHistory;

    /**
     * プレイヤーの最新の受け取り状態が、指定したボーナスIDと受け取り日時のものなら返す
     *
     * @param  string  $receivedAt  受け取り日時（Y-m-d H:i:s形式）
     */
    public function selectByPlayerAndBonusAndDate(
        int $sysPlayerId,
        string $mstVipLoginBonusId,
        string $receivedAt,
    ): ?TrxVipLoginBonusHistory;
}
