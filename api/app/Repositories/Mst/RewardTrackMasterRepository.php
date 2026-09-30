<?php

namespace App\Repositories\Mst;

use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusRewardTrack\Contracts\RewardTrackMasterRepositoryInterface;

class RewardTrackMasterRepository implements RewardTrackMasterRepositoryInterface
{
    public function selectActiveTracks(): array
    {
        $now = ClockUtility::nowToString();

        return DB::connection('mst')
            ->table('mst_reward_track')
            ->where('is_active', true)
            ->where('start_at', '<=', $now)
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', $now))
            ->orderByDesc('sort_desc')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function selectTrackById(string $trackId): ?array
    {
        $row = DB::connection('mst')
            ->table('mst_reward_track')
            ->where('id', $trackId)
            ->first();

        return $row ? (array) $row : null;
    }

    public function selectLinesByTrackId(string $trackId): array
    {
        return DB::connection('mst')
            ->table('mst_reward_track_line')
            ->where('mst_reward_track_id', $trackId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($r) => array_merge((array) $r, ['is_free' => (bool) ((array) $r)['is_free']]))
            ->all();
    }

    public function selectMilestonesByTrackId(string $trackId): array
    {
        return DB::connection('mst')
            ->table('mst_reward_track_milestone')
            ->where('mst_reward_track_id', $trackId)
            ->where('is_active', true)
            ->orderBy('required_progress')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function selectContentsByMilestoneIds(array $milestoneIds): array
    {
        if (empty($milestoneIds)) {
            return [];
        }

        return DB::connection('mst')
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
}
