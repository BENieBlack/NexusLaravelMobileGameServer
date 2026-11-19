<?php

namespace App\Repositories\Trx;

use App\Adapters\RewardTrack\RewardTrackAdapter;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusRewardTrack\DataTransferObjects\RewardTrack;
use NexusRewardTrack\Repositories\RewardTrackRepositoryInterface;

/**
 * RewardTrackRepositoryAdapter
 *
 * trx_reward_track（プレイヤーのトラック進行）の永続化。
 * パッケージ契約（RewardTrackRepositoryInterface）の実装。契約単位のため Model と 1 対 1 の規約の対象外（*RepositoryAdapter）。
 * 読み書きは接続を指定した DB::table で行い、行を DTO にして返す（UnitOfWork のキューは通さない）。書き込みは UseCase のトランザクション終了を待たず、呼んだ時点で実行される。
 */
class RewardTrackRepositoryAdapter implements RewardTrackRepositoryInterface
{
    public function findByPlayerAndTrack(int $sysPlayerId, string $mstRewardTrackId, string $connectionName): ?RewardTrack
    {
        $row = DB::connection($connectionName)
            ->table('trx_reward_track')
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_reward_track_id', $mstRewardTrackId)
            ->where('is_delete', false)
            ->first();

        return $row ? RewardTrackAdapter::toDto((array) $row) : null;
    }

    public function findAllByPlayer(int $sysPlayerId, string $connectionName): array
    {
        return DB::connection($connectionName)
            ->table('trx_reward_track')
            ->where('sys_player_id', $sysPlayerId)
            ->where('is_delete', false)
            ->get()
            ->map(fn ($r) => RewardTrackAdapter::toDto((array) $r))
            ->all();
    }

    public function upsertProgress(int $sysPlayerId, string $mstRewardTrackId, int $progress, string $connectionName): RewardTrack
    {
        $now = ClockUtility::nowToString();

        DB::connection($connectionName)->table('trx_reward_track')->upsert(
            [
                'sys_player_id' => $sysPlayerId,
                'mst_reward_track_id' => $mstRewardTrackId,
                'current_progress' => $progress,
                'is_delete' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['sys_player_id', 'mst_reward_track_id'],
            ['current_progress', 'updated_at']
        );

        return $this->findByPlayerAndTrack($sysPlayerId, $mstRewardTrackId, $connectionName)
            ?? throw new \LogicException('upsert 直後の行が取得できません: '.$mstRewardTrackId);
    }

    public function addProgress(int $sysPlayerId, string $mstRewardTrackId, int $delta, string $connectionName): RewardTrack
    {
        $now = ClockUtility::nowToString();

        DB::connection($connectionName)->table('trx_reward_track')->upsert(
            [
                'sys_player_id' => $sysPlayerId,
                'mst_reward_track_id' => $mstRewardTrackId,
                'current_progress' => $delta,
                'is_delete' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['sys_player_id', 'mst_reward_track_id'],
            [
                // $delta は int 型で、SQL に埋め込んでも注入にならない（upsert の更新式は束縛値にできない）
                /** @phpstan-ignore argument.type */
                'current_progress' => DB::raw('current_progress + '.$delta),
                'updated_at' => $now,
            ]
        );

        return $this->findByPlayerAndTrack($sysPlayerId, $mstRewardTrackId, $connectionName)
            ?? throw new \LogicException('upsert 直後の行が取得できません: '.$mstRewardTrackId);
    }
}
