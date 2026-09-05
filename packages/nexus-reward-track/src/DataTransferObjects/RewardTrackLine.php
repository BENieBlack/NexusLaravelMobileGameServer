<?php

namespace NexusRewardTrack\DataTransferObjects;

/**
 * プレイヤーの購入済みラインDTO
 */
readonly class RewardTrackLine
{
    public function __construct(
        public int $id,
        public int $sysPlayerId,
        public string $mstRewardTrackLineId,
        public int $mstInAppPurchaseId,
        public string $purchasedAt,
        public bool $isDelete,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    public function getMstRewardTrackLineId(): string
    {
        return $this->mstRewardTrackLineId;
    }

    public function getMstInAppPurchaseId(): int
    {
        return $this->mstInAppPurchaseId;
    }

    public function getPurchasedAt(): string
    {
        return $this->purchasedAt;
    }

    public function isDelete(): bool
    {
        return $this->isDelete;
    }
}
