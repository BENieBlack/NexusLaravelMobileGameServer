<?php

namespace NexusRewardTrack\DataTransferObjects;

/**
 * RewardTrackMaster
 *
 * 報酬トラックのマスター（mst_reward_track の1行）を表すDTO
 */
class RewardTrackMaster
{
    public function __construct(
        private readonly string $id,
        private readonly string $progressType,
        private readonly string $startAt,
        private readonly ?string $endAt,
        private readonly int $sortDesc,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getProgressType(): string
    {
        return $this->progressType;
    }

    public function getStartAt(): string
    {
        return $this->startAt;
    }

    public function getEndAt(): ?string
    {
        return $this->endAt;
    }

    public function getSortDesc(): int
    {
        return $this->sortDesc;
    }
}
