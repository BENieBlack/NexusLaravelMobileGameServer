<?php

namespace NexusVip\Repositories;

/**
 * VIPポイントログRepositoryインターフェース
 */
interface VipPointLogRepositoryInterface
{
    /**
     * VIPポイント変動ログを記録
     *
     * @param string $uniqueRequestId
     * @param int $sysPlayerId
     * @param int $beforeLevel
     * @param int $afterLevel
     * @param int $beforePoint
     * @param int $afterPoint
     * @param int $pointDiff
     * @param string $reason
     * @param array $metadata
     * @return void
     */
    public function log(
        string $uniqueRequestId,
        int $sysPlayerId,
        int $beforeLevel,
        int $afterLevel,
        int $beforePoint,
        int $afterPoint,
        int $pointDiff,
        string $reason,
        array $metadata = []
    ): void;

    /**
     * プレイヤーのVIPポイント履歴を取得
     *
     * @param int $sysPlayerId
     * @param int $limit
     * @return array
     */
    public function getHistory(int $sysPlayerId, int $limit = 100): array;
}
