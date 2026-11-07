<?php

namespace App\Repositories\Mst;

use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusEvent\Constants\EventContentType;
use NexusEvent\Services\EventPeriodResolver;
use NexusRewardTrack\Contracts\RewardTrackMasterRepositoryInterface;
use NexusRewardTrack\DataTransferObjects\RewardTrackContentMaster;
use NexusRewardTrack\DataTransferObjects\RewardTrackLineMaster;
use NexusRewardTrack\DataTransferObjects\RewardTrackMaster;
use NexusRewardTrack\DataTransferObjects\RewardTrackMilestoneMaster;

class RewardTrackMasterRepository implements RewardTrackMasterRepositoryInterface
{
    public function __construct(
        private readonly EventPeriodResolver $eventPeriodResolver,
    ) {}

    public function selectActiveTracks(): array
    {
        // イベントに入っているトラックは、イベントの期間で判定するため、期間はSQLで絞らず、上書きした後にPHPで判定する
        $tracks = DB::connection('mst')
            ->table('mst_reward_track')
            ->where('is_active', true)
            ->orderByDesc('sort_desc')
            ->get()
            ->map(fn ($row) => $this->toTrack((array) $row))
            ->filter(fn (RewardTrackMaster $track) => ClockUtility::isWithin($track->getStartAt(), $track->getEndAt()))
            ->all();

        return array_values($tracks);
    }

    public function selectTrackById(string $trackId): ?RewardTrackMaster
    {
        $row = DB::connection('mst')
            ->table('mst_reward_track')
            ->where('id', $trackId)
            ->first();

        return $row ? $this->toTrack((array) $row) : null;
    }

    public function selectLinesByTrackId(string $trackId): array
    {
        return array_values(DB::connection('mst')
            ->table('mst_reward_track_line')
            ->where('mst_reward_track_id', $trackId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($row) => $this->toLine((array) $row))
            ->all());
    }

    public function selectMilestonesByTrackId(string $trackId): array
    {
        return array_values(DB::connection('mst')
            ->table('mst_reward_track_milestone')
            ->where('mst_reward_track_id', $trackId)
            ->where('is_active', true)
            ->orderBy('required_progress')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($row) => $this->toMilestone((array) $row))
            ->all());
    }

    public function selectContentsByMilestoneIds(array $milestoneIds): array
    {
        if (empty($milestoneIds)) {
            return [];
        }

        return array_values(DB::connection('mst')
            ->table('mst_reward_track_content')
            ->whereIn('mst_reward_track_milestone_id', $milestoneIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($row) => $this->toContent((array) $row))
            ->all());
    }

    public function selectFreeLineId(string $trackId): ?string
    {
        $row = DB::connection('mst')
            ->table('mst_reward_track_line')
            ->where('mst_reward_track_id', $trackId)
            ->where('is_free', true)
            ->where('is_active', true)
            ->first();

        return $row ? ((array) $row)['id'] : null;
    }

    /**
     * トラックがイベントに入っていれば、イベントの期間（start_at / end_at）で上書きする
     *
     * @param  array<string, mixed>  $track
     * @return array<string, mixed>
     */
    private function applyEventPeriod(array $track): array
    {
        $period = $this->eventPeriodResolver->resolve(EventContentType::REWARD_TRACK, (string) $track['id']);

        if ($period === null) {
            return $track;
        }

        $track['start_at'] = $period->getStartAt();
        $track['end_at'] = $period->getEndAt();

        return $track;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toTrack(array $row): RewardTrackMaster
    {
        $row = $this->applyEventPeriod($row);

        return new RewardTrackMaster(
            id: (string) $row['id'],
            progressType: (string) $row['progress_type'],
            startAt: (string) $row['start_at'],
            endAt: $row['end_at'] === null ? null : (string) $row['end_at'],
            sortDesc: (int) $row['sort_desc'],
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toLine(array $row): RewardTrackLineMaster
    {
        return new RewardTrackLineMaster(
            id: (string) $row['id'],
            mstRewardTrackId: (string) $row['mst_reward_track_id'],
            isFree: (bool) $row['is_free'],
            mstInAppPurchaseId: $row['mst_in_app_purchase_id'] === null ? null : (int) $row['mst_in_app_purchase_id'],
            sortOrder: (int) $row['sort_order'],
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toMilestone(array $row): RewardTrackMilestoneMaster
    {
        return new RewardTrackMilestoneMaster(
            id: (string) $row['id'],
            mstRewardTrackId: (string) $row['mst_reward_track_id'],
            requiredProgress: (int) $row['required_progress'],
            sortOrder: (int) $row['sort_order'],
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toContent(array $row): RewardTrackContentMaster
    {
        /** @var array<string, mixed>|null $contentOption */
        $contentOption = isset($row['content_option']) ? json_decode((string) $row['content_option'], true) : null;

        return new RewardTrackContentMaster(
            mstRewardTrackMilestoneId: (string) $row['mst_reward_track_milestone_id'],
            mstRewardTrackLineId: (string) $row['mst_reward_track_line_id'],
            contentType: (string) $row['content_type'],
            contentMstId: (string) $row['content_mst_id'],
            contentOption: $contentOption,
            contentQuantity: (int) $row['content_quantity'],
            amount: (int) $row['amount'],
            sortOrder: (int) $row['sort_order'],
        );
    }
}
