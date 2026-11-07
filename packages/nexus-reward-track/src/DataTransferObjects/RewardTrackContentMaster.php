<?php

namespace NexusRewardTrack\DataTransferObjects;

/**
 * RewardTrackContentMaster
 *
 * 報酬トラックの報酬（mst_reward_track_content の1行）を表すDTO
 */
class RewardTrackContentMaster
{
    /**
     * @param  array<string, mixed>|null  $contentOption
     */
    public function __construct(
        private readonly string $mstRewardTrackMilestoneId,
        private readonly string $mstRewardTrackLineId,
        private readonly string $contentType,
        private readonly string $contentMstId,
        private readonly ?array $contentOption,
        private readonly int $contentQuantity,
        private readonly int $amount,
        private readonly int $sortOrder,
    ) {}

    public function getMstRewardTrackMilestoneId(): string
    {
        return $this->mstRewardTrackMilestoneId;
    }

    public function getMstRewardTrackLineId(): string
    {
        return $this->mstRewardTrackLineId;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContentMstId(): string
    {
        return $this->contentMstId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContentOption(): ?array
    {
        return $this->contentOption;
    }

    public function getContentQuantity(): int
    {
        return $this->contentQuantity;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
