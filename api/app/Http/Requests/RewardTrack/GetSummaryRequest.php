<?php

namespace App\Http\Requests\RewardTrack;

use App\Http\Requests\_BaseRequest;

class GetSummaryRequest extends _BaseRequest
{
    public function rules(): array
    {
        return [
            'track_id' => ['required', 'string', 'max:64'],
        ];
    }

    public function getTrackId(): string
    {
        return $this->input('track_id');
    }
}
