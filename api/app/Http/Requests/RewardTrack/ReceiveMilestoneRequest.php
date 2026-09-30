<?php

namespace App\Http\Requests\RewardTrack;

use App\Http\Requests\_BaseRequest;

class ReceiveMilestoneRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mst_reward_track_milestone_id' => ['required', 'string', 'max:64'],
            'mst_reward_track_line_id' => ['required', 'string', 'max:64'],
        ];
    }

    public function getMstRewardTrackMilestoneId(): string
    {
        return $this->input('mst_reward_track_milestone_id');
    }

    public function getMstRewardTrackLineId(): string
    {
        return $this->input('mst_reward_track_line_id');
    }
}
