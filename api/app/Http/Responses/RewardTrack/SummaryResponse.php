<?php

namespace App\Http\Responses\RewardTrack;

use App\Http\Responses\_BaseResponse;

class SummaryResponse extends _BaseResponse
{
    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        private readonly array $summary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'track' => $this->summary['track'],
            'lines' => $this->summary['lines'],
            'milestones' => $this->summary['milestones'],
            'current_progress' => $this->summary['current_progress'],
            'owned_line_id_list' => $this->summary['owned_line_ids'],
            'received_key_set' => $this->summary['received_key_set'],
        ];
    }
}
