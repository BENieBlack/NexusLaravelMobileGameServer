<?php

namespace App\Domain\RewardTrack\Responses;

use App\Domain\_BaseResponse;
use NexusRewardTrack\DataTransferObjects\RewardTrackContentMaster;
use NexusRewardTrack\DataTransferObjects\RewardTrackSummary;

class GetSummaryResponse extends _BaseResponse
{
    public function __construct(
        private readonly RewardTrackSummary $summary,
    ) {}

    /**
     * UseCaseの結果（トラックのサマリー）からレスポンスを生成
     */
    public static function from(RewardTrackSummary $summary): self
    {
        return new self($summary);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $track = $this->summary->getTrack();

        $lineList = [];
        foreach ($this->summary->getLines() as $line) {
            $lineList[] = [
                'id' => $line->getId(),
                'mst_reward_track_id' => $line->getMstRewardTrackId(),
                'is_free' => $line->getIsFree(),
                'mst_in_app_purchase_id' => $line->getMstInAppPurchaseId(),
                'sort_order' => $line->getSortOrder(),
            ];
        }

        $milestoneList = [];
        foreach ($this->summary->getMilestones() as $milestone) {
            $contentsByLine = [];
            foreach ($this->summary->getContentsByLine($milestone->getId()) as $lineId => $contents) {
                $contentsByLine[$lineId] = array_map(
                    fn (RewardTrackContentMaster $content) => [
                        'mst_reward_track_milestone_id' => $content->getMstRewardTrackMilestoneId(),
                        'mst_reward_track_line_id' => $content->getMstRewardTrackLineId(),
                        'content_type' => $content->getContentType(),
                        'content_mst_id' => $content->getContentMstId(),
                        'content_option' => $content->getContentOption(),
                        'content_quantity' => $content->getContentQuantity(),
                        'amount' => $content->getAmount(),
                        'sort_order' => $content->getSortOrder(),
                    ],
                    $contents
                );
            }

            $milestoneList[] = [
                'id' => $milestone->getId(),
                'mst_reward_track_id' => $milestone->getMstRewardTrackId(),
                'required_progress' => $milestone->getRequiredProgress(),
                'sort_order' => $milestone->getSortOrder(),
                'contents' => $contentsByLine,
            ];
        }

        return [
            'track' => [
                'id' => $track->getId(),
                'progress_type' => $track->getProgressType(),
                'start_at' => $track->getStartAt(),
                'end_at' => $track->getEndAt(),
                'sort_desc' => $track->getSortDesc(),
            ],
            'line_list' => $lineList,
            'milestone_list' => $milestoneList,
            'current_progress' => $this->summary->getCurrentProgress(),
            'owned_line_id_list' => $this->summary->getOwnedLineIds(),
            'received_key_set' => $this->summary->getReceivedKeySet(),
        ];
    }
}
