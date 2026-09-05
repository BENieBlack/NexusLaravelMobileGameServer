<?php

namespace App\Repositories\Trx;

use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusRewardTrack\DataTransferObjects\RewardTrackMilestone;
use NexusRewardTrack\Repositories\RewardTrackMilestoneRepositoryInterface;

class TrxRewardTrackMilestoneRepository implements RewardTrackMilestoneRepositoryInterface
{
    public function findByPlayerAndTrack(int $sysPlayerId, string $mstRewardTrackId, string $connectionName): array
    {
        // trx_reward_track_milestone には mst_reward_track_id がないため
        // mst_reward_track_milestone テーブルとJOINして絞り込む
        return DB::connection($connectionName)
            ->table('trx_reward_track_milestone as trm')
            ->join(
                DB::connection('mst')->getDatabaseName().'.mst_reward_track_milestone as mstm',
                'trm.mst_reward_track_milestone_id', '=', 'mstm.id'
            )
            ->where('trm.sys_player_id', $sysPlayerId)
            ->where('mstm.mst_reward_track_id', $mstRewardTrackId)
            ->where('trm.is_delete', false)
            ->select('trm.*')
            ->get()
            ->map(fn ($r) => $this->toDto((array) $r))
            ->all();
    }

    public function hasReceived(int $sysPlayerId, string $mstRewardTrackMilestoneId, string $mstRewardTrackLineId, string $connectionName): bool
    {
        return DB::connection($connectionName)
            ->table('trx_reward_track_milestone')
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_reward_track_milestone_id', $mstRewardTrackMilestoneId)
            ->where('mst_reward_track_line_id', $mstRewardTrackLineId)
            ->where('is_delete', false)
            ->exists();
    }

    public function insertReceipt(int $sysPlayerId, string $mstRewardTrackMilestoneId, string $mstRewardTrackLineId, string $receivedAt, string $connectionName): RewardTrackMilestone
    {
        $now = ClockUtility::nowToString();

        $id = DB::connection($connectionName)->table('trx_reward_track_milestone')->insertGetId([
            'sys_player_id' => $sysPlayerId,
            'mst_reward_track_milestone_id' => $mstRewardTrackMilestoneId,
            'mst_reward_track_line_id' => $mstRewardTrackLineId,
            'received_at' => $receivedAt,
            'is_delete' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::connection($connectionName)
            ->table('trx_reward_track_milestone')
            ->where('id', $id)
            ->first();

        return $this->toDto((array) $row);
    }

    public function findReceivedKeySet(int $sysPlayerId, string $mstRewardTrackId, string $connectionName): array
    {
        // mst と trx は別のDBサーバに載っているため、跨いだJOINはできない
        // （'Unknown database' で落ちる）。マスター側で対象マイルストーンを引いてから、
        // そのIDでシャード側を絞る
        $milestoneIds = DB::connection('mst')
            ->table('mst_reward_track_milestone')
            ->where('mst_reward_track_id', $mstRewardTrackId)
            ->pluck('id')
            ->all();

        if (empty($milestoneIds)) {
            return [];
        }

        $rows = DB::connection($connectionName)
            ->table('trx_reward_track_milestone')
            ->where('sys_player_id', $sysPlayerId)
            ->whereIn('mst_reward_track_milestone_id', $milestoneIds)
            ->where('is_delete', false)
            ->select('mst_reward_track_milestone_id', 'mst_reward_track_line_id')
            ->get();

        $keySet = [];
        foreach ($rows as $row) {
            $key = $row->mst_reward_track_milestone_id.':'.$row->mst_reward_track_line_id;
            $keySet[$key] = true;
        }

        return $keySet;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toDto(array $row): RewardTrackMilestone
    {
        return new RewardTrackMilestone(
            id: (int) $row['id'],
            sysPlayerId: (int) $row['sys_player_id'],
            mstRewardTrackMilestoneId: $row['mst_reward_track_milestone_id'],
            mstRewardTrackLineId: $row['mst_reward_track_line_id'],
            receivedAt: $row['received_at'],
            isDelete: (bool) $row['is_delete'],
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }
}
