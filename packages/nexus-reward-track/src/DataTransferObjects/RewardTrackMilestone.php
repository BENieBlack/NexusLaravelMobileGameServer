<?php

namespace NexusRewardTrack\DataTransferObjects;

/**
 * プレイヤーの受け取り済みマイルストーンDTO
 */
readonly class RewardTrackMilestone
{
    public function __construct(
        public int $id,
        public int $sysPlayerId,
        public string $mstRewardTrackMilestoneId,
        public string $mstRewardTrackLineId,
        public string $receivedAt,
        public bool $isDelete,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public function getId(): int { return $this->id; }
    public function getSysPlayerId(): int { return $this->sysPlayerId; }
    public function getMstRewardTrackMilestoneId(): string { return $this->mstRewardTrackMilestoneId; }
    public function getMstRewardTrackLineId(): string { return $this->mstRewardTrackLineId; }
    public function getReceivedAt(): string { return $this->receivedAt; }
    public function isDelete(): bool { return $this->isDelete; }
}
