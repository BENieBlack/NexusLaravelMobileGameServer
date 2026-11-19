<?php

namespace App\Repositories\Trx;

use App\Adapters\RewardTrack\RewardTrackLineAdapter;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusRewardTrack\DataTransferObjects\RewardTrackLine;
use NexusRewardTrack\Repositories\RewardTrackLineRepositoryInterface;

/**
 * RewardTrackLineRepositoryAdapter
 *
 * trx_reward_track_line（ライン解放状態）の永続化。
 * パッケージ契約（RewardTrackLineRepositoryInterface）の実装。契約単位のため Model と 1 対 1 の規約の対象外（*RepositoryAdapter）。
 * 読み書きは接続を指定した DB::table で行い、行を DTO にして返す（UnitOfWork のキューは通さない）。書き込みは UseCase のトランザクション終了を待たず、呼んだ時点で実行される。
 */
class RewardTrackLineRepositoryAdapter implements RewardTrackLineRepositoryInterface
{
    public function findAllByPlayer(int $sysPlayerId, string $connectionName): array
    {
        return DB::connection($connectionName)
            ->table('trx_reward_track_line')
            ->where('sys_player_id', $sysPlayerId)
            ->where('is_delete', false)
            ->get()
            ->map(fn ($r) => RewardTrackLineAdapter::toDto((array) $r))
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
            'sys_player_id' => $sysPlayerId,
            'mst_reward_track_line_id' => $mstRewardTrackLineId,
            'mst_in_app_purchase_id' => $mstInAppPurchaseId,
            'purchased_at' => $purchasedAt,
            'is_delete' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = DB::connection($connectionName)
            ->table('trx_reward_track_line')
            ->where('id', $id)
            ->first();

        return RewardTrackLineAdapter::toDto((array) $row);
    }
}
