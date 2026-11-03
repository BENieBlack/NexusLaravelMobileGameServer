<?php

namespace App\Repositories\Log;

use App\Models\Log\LogVipLoginBonusReceive;
use Nexus\Core\Support\CustomCollection;

/**
 * LogVipLoginBonusReceiveRepository
 *
 * VIPログインボーナスの受け取りログを管理するRepository
 *
 * @extends _BaseLogRepository<LogVipLoginBonusReceive>
 */
class LogVipLoginBonusReceiveRepository extends _BaseLogRepository
{
    protected string $modelClass = LogVipLoginBonusReceive::class;

    /**
     * 通常ログであることを明示
     */
    protected bool $isPurchaseLog = false;

    /**
     * 受け取った報酬1件分のログを積む（書き込みはQueryManager）
     *
     * @param  string  $receivedAt  受け取り日時（Y-m-d H:i:s形式）
     */
    public function insertReceive(
        int $sysPlayerId,
        string $mstVipLoginBonusId,
        int $day,
        int $vipLevel,
        string $receivedAt,
        string $rewardType,
        string $rewardMstId,
        int $rewardAmount,
        bool $isPaid,
    ): LogVipLoginBonusReceive {
        $model = new LogVipLoginBonusReceive([
            'sys_player_id' => $sysPlayerId,
            'mst_vip_login_bonus_id' => $mstVipLoginBonusId,
            'day' => $day,
            'vip_level' => $vipLevel,
            'received_at' => $receivedAt,
            'reward_type' => $rewardType,
            'reward_mst_id' => $rewardMstId,
            'reward_amount' => $rewardAmount,
            'is_paid' => $isPaid,
        ]);

        $this->setModel($model);

        return $model;
    }

    /**
     * プレイヤーの受け取りログを取得する
     *
     * @return CustomCollection<int, LogVipLoginBonusReceive>
     */
    public function selectAllByPlayer(): CustomCollection
    {
        return $this->queryOrMemory();
    }
}
