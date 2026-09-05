<?php

namespace App\Http\Requests\RewardTrack;

use App\Http\Requests\_BaseRequest;

class GetSummaryRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mst_reward_track_id' => ['required', 'string', 'max:64'],
        ];
    }

    public function getMstRewardTrackId(): string
    {
        return $this->input('mst_reward_track_id');
    }
}
