<?php

namespace NexusRewardTrack\DataTransferObjects;

/**
 * RewardTrackMilestoneMaster
 *
 * 報酬トラックのマイルストーン（mst_reward_track_milestone の1行）を表すDTO
 */
class RewardTrackMilestoneMaster
{
    public function __construct(
        private readonly string $id,
        private readonly string $mstRewardTrackId,
        private readonly int $requiredProgress,
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

    public function getRequiredProgress(): int
    {
        return $this->requiredProgress;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
