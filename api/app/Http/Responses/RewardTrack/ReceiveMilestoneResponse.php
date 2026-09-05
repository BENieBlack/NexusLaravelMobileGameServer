<?php

namespace App\Http\Responses\RewardTrack;

use App\Http\Responses\_BaseResponse;
use NexusRewardTrack\DataTransferObjects\RewardTrackMilestone;

class ReceiveMilestoneResponse extends _BaseResponse
{
    public function __construct(
        private readonly RewardTrackMilestone $milestone,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mst_reward_track_milestone_id' => $this->milestone->getMstRewardTrackMilestoneId(),
            'mst_reward_track_line_id' => $this->milestone->getMstRewardTrackLineId(),
            'received_at' => $this->milestone->getReceivedAt(),
        ];
    }
}
