<?php

namespace App\Repositories\Mst;

use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusEvent\Constants\EventContentType;
use NexusEvent\Services\EventPeriodResolver;
use NexusRewardTrack\Contracts\RewardTrackMasterRepositoryInterface;

class RewardTrackMasterRepository implements RewardTrackMasterRepositoryInterface
{
    public function __construct(
        private readonly EventPeriodResolver $eventPeriodResolver,
    ) {}

    public function selectActiveTracks(): array
    {
        // イベントに入っているトラックは、イベントの期間で判定するため、期間はSQLで絞らず、上書きした後にPHPで判定する
        $rows = DB::connection('mst')
            ->table('mst_reward_track')
            ->where('is_active', true)
            ->orderByDesc('sort_desc')
            ->get()
            ->map(fn ($r) => $this->applyEventPeriod((array) $r))
            ->filter(fn (array $track) => ClockUtility::isWithin($track['start_at'], $track['end_at']))
            ->values()
            ->all();

        /** @var array<array{id: string, progress_type: string, start_at: string, end_at: ?string, sort_desc: int}> $rows */
        return $rows;
    }

    public function selectTrackById(string $trackId): ?array
    {
        $row = DB::connection('mst')
            ->table('mst_reward_track')
            ->where('id', $trackId)
            ->first();

        if (! $row) {
            return null;
        }

        /** @var array{id: string, progress_type: string, start_at: string, end_at: ?string} $track */
        $track = $this->applyEventPeriod((array) $row);

        return $track;
    }

    public function selectLinesByTrackId(string $trackId): array
    {
        $rows = DB::connection('mst')
            ->table('mst_reward_track_line')
            ->where('mst_reward_track_id', $trackId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($r) => array_merge((array) $r, ['is_free' => (bool) ((array) $r)['is_free']]))
            ->all();

        /** @var array<array{id: string, mst_reward_track_id: string, is_free: bool, mst_in_app_purchase_id: ?int, sort_order: int}> $rows */
        return $rows;
    }

    public function selectMilestonesByTrackId(string $trackId): array
    {
        $rows = DB::connection('mst')
            ->table('mst_reward_track_milestone')
            ->where('mst_reward_track_id', $trackId)
            ->where('is_active', true)
            ->orderBy('required_progress')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        /** @var array<array{id: string, mst_reward_track_id: string, required_progress: int, sort_order: int}> $rows */
        return $rows;
    }

    public function selectContentsByMilestoneIds(array $milestoneIds): array
    {
        if (empty($milestoneIds)) {
            return [];
        }

        $rows = DB::connection('mst')
            ->table('mst_reward_track_content')
            ->whereIn('mst_reward_track_milestone_id', $milestoneIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($r) {
                $arr = (array) $r;
                $arr['content_option'] = isset($arr['content_option'])
                    ? json_decode($arr['content_option'], true)
                    : null;

                return $arr;
            })
            ->all();

        /** @var array<array{mst_reward_track_milestone_id: string, mst_reward_track_line_id: string, content_type: string, content_mst_id: string, content_option: array<string, mixed>|null, content_quantity: int, amount: int, sort_order: int}> $rows */
        return $rows;
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
}
