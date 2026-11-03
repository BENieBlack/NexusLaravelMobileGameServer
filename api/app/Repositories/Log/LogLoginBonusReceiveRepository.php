<?php

namespace App\Repositories\Log;

use App\Models\Log\LogLoginBonusReceive;
use Nexus\Core\Support\CustomCollection;

/**
 * LogLoginBonusReceiveRepository
 *
 * ログインボーナス（通常・カムバック）の受け取りログを管理するRepository
 *
 * @extends _BaseLogRepository<LogLoginBonusReceive>
 */
class LogLoginBonusReceiveRepository extends _BaseLogRepository
{
    protected string $modelClass = LogLoginBonusReceive::class;

    /**
     * 通常ログであることを明示
     */
    protected bool $isPurchaseLog = false;

    /**
     * 受け取った報酬1件分のログを積む（書き込みはQueryManager）
     *
     * @param  string  $type  daily / comeback
     * @param  string  $receivedAt  受け取り日時（Y-m-d H:i:s形式）
     */
    public function insertReceive(
        int $sysPlayerId,
        string $type,
        string $mstLoginBonusId,
        int $day,
        ?int $absentDays,
        string $receivedAt,
        string $rewardType,
        string $rewardMstId,
        int $rewardAmount,
        bool $isPaid,
    ): LogLoginBonusReceive {
        $model = new LogLoginBonusReceive([
            'sys_player_id' => $sysPlayerId,
            'type' => $type,
            'mst_login_bonus_id' => $mstLoginBonusId,
            'day' => $day,
            'absent_days' => $absentDays,
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
     * @return CustomCollection<int, LogLoginBonusReceive>
     */
    public function selectAllByPlayer(): CustomCollection
    {
        return $this->queryOrMemory();
    }
}
