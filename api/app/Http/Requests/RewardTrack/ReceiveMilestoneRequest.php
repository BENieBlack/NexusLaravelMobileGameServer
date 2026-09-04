<?php

namespace App\Http\Requests\RewardTrack;

use App\Http\Requests\_BaseRequest;

class ReceiveMilestoneRequest extends _BaseRequest
{
    public function rules(): array
    {
        return [
            'milestone_id' => ['required', 'string', 'max:64'],
            'line_id'      => ['required', 'string', 'max:64'],
        ];
    }

    public function getMilestoneId(): string
    {
        return $this->input('milestone_id');
    }

    public function getLineId(): string
    {
        return $this->input('line_id');
    }
}
