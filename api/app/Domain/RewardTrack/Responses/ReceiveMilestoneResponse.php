<?php

namespace App\Domain\RewardTrack\Responses;

use App\Domain\_BaseResponse;
use NexusRewardTrack\DataTransferObjects\RewardTrackMilestone;

class ReceiveMilestoneResponse extends _BaseResponse
{
    public function __construct(
        private readonly RewardTrackMilestone $milestone,
    ) {}

    /**
     * UseCaseの結果（受け取ったマイルストーン）からレスポンスを生成
     */
    public static function from(RewardTrackMilestone $milestone): self
    {
        return new self($milestone);
    }

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
