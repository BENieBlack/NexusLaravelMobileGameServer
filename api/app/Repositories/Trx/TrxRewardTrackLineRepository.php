<?php

namespace App\Repositories\Trx;

use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusRewardTrack\DataTransferObjects\RewardTrackLine;
use NexusRewardTrack\Repositories\RewardTrackLineRepositoryInterface;

class TrxRewardTrackLineRepository implements RewardTrackLineRepositoryInterface
{
    public function findAllByPlayer(int $sysPlayerId, string $connectionName): array
    {
        return DB::connection($connectionName)
            ->table('trx_reward_track_line')
            ->where('sys_player_id', $sysPlayerId)
            ->where('is_delete', false)
            ->get()
            ->map(fn ($r) => $this->toDto((array) $r))
            ->all();
    }

    public function hasLine(int $sysPlayerId, string $mstRewardTrackLineId, string $connectionName): bool
    {
        return DB::connection($connectionName)
            ->table('trx_reward_track_line')
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_reward_track_line_id', $mstRewardTrackLineId)
            ->where('is_delete', false)
            ->exists();
    }

    public function findOwnedLineIds(int $sysPlayerId, array $mstRewardTrackLineIds, string $connectionName): array
    {
        if (empty($mstRewardTrackLineIds)) {
            return [];
        }

        return DB::connection($connectionName)
            ->table('trx_reward_track_line')
            ->where('sys_player_id', $sysPlayerId)
            ->whereIn('mst_reward_track_line_id', $mstRewardTrackLineIds)
            ->where('is_delete', false)
            ->pluck('mst_reward_track_line_id')
            ->all();
    }

    public function insertLine(int $sysPlayerId, string $mstRewardTrackLineId, int $mstInAppPurchaseId, string $purchasedAt, string $connectionName): RewardTrackLine
    {
        $now = ClockUtility::nowToString();

        $id = DB::connection($connectionName)->table('trx_reward_track_line')->insertGetId([
            'sys_player_id'            => $sysPlayerId,
            'mst_reward_track_line_id' => $mstRewardTrackLineId,
            'mst_in_app_purchase_id'   => $mstInAppPurchaseId,
            'purchased_at'             => $purchasedAt,
            'is_delete'                => false,
            'created_at'               => $now,
            'updated_at'               => $now,
        ]);

        $row = DB::connection($connectionName)
            ->table('trx_reward_track_line')
            ->where('id', $id)
            ->first();

        return $this->toDto((array) $row);
    }

    private function toDto(array $row): RewardTrackLine
    {
        return new RewardTrackLine(
            id:                   (int) $row['id'],
            sysPlayerId:          (int) $row['sys_player_id'],
            mstRewardTrackLineId: $row['mst_reward_track_line_id'],
            mstInAppPurchaseId:   (int) $row['mst_in_app_purchase_id'],
            purchasedAt:          $row['purchased_at'],
            isDelete:             (bool) $row['is_delete'],
            createdAt:            $row['created_at'] ?? null,
            updatedAt:            $row['updated_at'] ?? null,
        );
    }
}
