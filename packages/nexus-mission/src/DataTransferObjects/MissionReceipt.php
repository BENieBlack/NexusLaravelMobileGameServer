<?php

namespace NexusMission\DataTransferObjects;

/**
 * MissionReceipt
 *
 * ミッションの報酬を受け取った記録（trx_mission の1行）
 */
class MissionReceipt
{
    public function __construct(
        private readonly int $sysPlayerId,
        private readonly string $mstMissionId,
        private readonly string $receivedAt,
    ) {}

    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    public function getMstMissionId(): string
    {
        return $this->mstMissionId;
    }

    /**
     * 受け取った日時（Y-m-d H:i:s形式）
     */
    public function getReceivedAt(): string
    {
        return $this->receivedAt;
    }
}
