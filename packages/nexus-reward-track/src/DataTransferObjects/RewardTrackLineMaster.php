<?php

namespace NexusRewardTrack\DataTransferObjects;

/**
 * RewardTrackLineMaster
 *
 * 報酬トラックのライン（mst_reward_track_line の1行）を表すDTO
 */
class RewardTrackLineMaster
{
    public function __construct(
        private readonly string $id,
        private readonly string $mstRewardTrackId,
        private readonly bool $isFree,
        private readonly ?int $mstInAppPurchaseId,
        private readonly int $sortOrder,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getMstRewardTrackId(): string
    {
        return $this->mstRewardTrackId;
    }

    public function getIsFree(): bool
    {
        return $this->isFree;
    }

    public function getMstInAppPurchaseId(): ?int
    {
        return $this->mstInAppPurchaseId;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
