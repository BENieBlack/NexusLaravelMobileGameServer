<?php

namespace App\Repositories\Trx;

use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusRewardTrack\DataTransferObjects\RewardTrack;
use NexusRewardTrack\Repositories\RewardTrackRepositoryInterface;

class TrxRewardTrackRepository implements RewardTrackRepositoryInterface
{
    public function findByPlayerAndTrack(int $sysPlayerId, string $mstRewardTrackId, string $connectionName): ?RewardTrack
    {
        $row = DB::connection($connectionName)
            ->table('trx_reward_track')
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_reward_track_id', $mstRewardTrackId)
            ->where('is_delete', false)
            ->first();

        return $row ? $this->toDto((array) $row) : null;
    }

    public function findAllByPlayer(int $sysPlayerId, string $connectionName): array
    {
        return DB::connection($connectionName)
            ->table('trx_reward_track')
            ->where('sys_player_id', $sysPlayerId)
            ->where('is_delete', false)
            ->get()
            ->map(fn ($r) => $this->toDto((array) $r))
            ->all();
    }

    public function upsertProgress(int $sysPlayerId, string $mstRewardTrackId, int $progress, string $connectionName): RewardTrack
    {
        $now = ClockUtility::nowToString();

        DB::connection($connectionName)->table('trx_reward_track')->upsert(
            [
                'sys_player_id'       => $sysPlayerId,
                'mst_reward_track_id' => $mstRewardTrackId,
                'current_progress'    => $progress,
                'is_delete'           => false,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
            ['sys_player_id', 'mst_reward_track_id'],
            ['current_progress', 'updated_at']
        );

        return $this->findByPlayerAndTrack($sysPlayerId, $mstRewardTrackId, $connectionName);
    }

    public function addProgress(int $sysPlayerId, string $mstRewardTrackId, int $delta, string $connectionName): RewardTrack
    {
        $now = ClockUtility::nowToString();

        DB::connection($connectionName)->table('trx_reward_track')->upsert(
            [
                'sys_player_id'       => $sysPlayerId,
                'mst_reward_track_id' => $mstRewardTrackId,
                'current_progress'    => $delta,
                'is_delete'           => false,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
            ['sys_player_id', 'mst_reward_track_id'],
            ['current_progress' => DB::raw("current_progress + {$delta}"), 'updated_at' => $now]
        );

        return $this->findByPlayerAndTrack($sysPlayerId, $mstRewardTrackId, $connectionName);
    }

    private function toDto(array $row): RewardTrack
    {
        return new RewardTrack(
            id:               (int) $row['id'],
            sysPlayerId:      (int) $row['sys_player_id'],
            mstRewardTrackId: $row['mst_reward_track_id'],
            currentProgress:  (int) $row['current_progress'],
            isDelete:         (bool) $row['is_delete'],
            createdAt:        $row['created_at'] ?? null,
            updatedAt:        $row['updated_at'] ?? null,
        );
    }
}
