<?php

namespace NexusRewardTrack\DataTransferObjects;

/**
 * プレイヤーのトラック進捗DTO
 */
readonly class RewardTrack
{
    public function __construct(
        public int $id,
        public int $sysPlayerId,
        public string $mstRewardTrackId,
        public int $currentProgress,
        public bool $isDelete,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public function getId(): int { return $this->id; }
    public function getSysPlayerId(): int { return $this->sysPlayerId; }
    public function getMstRewardTrackId(): string { return $this->mstRewardTrackId; }
    public function getCurrentProgress(): int { return $this->currentProgress; }
    public function isDelete(): bool { return $this->isDelete; }
}
