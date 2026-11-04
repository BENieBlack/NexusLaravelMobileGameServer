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
     * UseCaseの結果（トラックのサマリー）からレスポンスを生成
     *
     * @param  array<string, mixed>  $summary
     */
    public static function fromResult(array $summary): self
    {
        return new self($summary);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'track' => $this->summary['track'],
            'line_list' => $this->summary['lines'],
            'milestone_list' => $this->summary['milestones'],
            'current_progress' => $this->summary['current_progress'],
            'owned_line_id_list' => $this->summary['owned_line_ids'],
            'received_key_set' => $this->summary['received_key_set'],
        ];
    }
}
