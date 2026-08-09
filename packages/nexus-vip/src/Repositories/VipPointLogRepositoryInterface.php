<?php

namespace NexusVip\Repositories;

/**
 * VIPポイントログRepositoryインターフェース
 */
interface VipPointLogRepositoryInterface
{
    /**
     * VIPポイント変動ログを記録
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
     */
    public function getHistory(int $sysPlayerId, int $limit = 100): array;
}
