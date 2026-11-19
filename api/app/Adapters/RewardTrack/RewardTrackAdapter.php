<?php

namespace App\Adapters\RewardTrack;

use NexusRewardTrack\DataTransferObjects\RewardTrack;

/**
 * RewardTrackAdapter
 *
 * trx_reward_track 1 行を DTO（RewardTrack）へ変換するアダプター。
 * 対応する Model を持たないテーブルをパッケージ契約の Repository（*RepositoryAdapter）が
 * DB::table で読むため、入力は Model ではなく行の連想配列。変換は Repository 側に書かずここへ集める（ConverterLocationTest）。
 */
class RewardTrackAdapter
{
    /**
     * DB の行（連想配列）から DTO に変換する
     *
     * @param  array<string, mixed>  $row
     */
    public static function toDto(array $row): RewardTrack
    {
        return new RewardTrack(
            id: (int) $row['id'],
            sysPlayerId: (int) $row['sys_player_id'],
            mstRewardTrackId: $row['mst_reward_track_id'],
            currentProgress: (int) $row['current_progress'],
            isDelete: (bool) $row['is_delete'],
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }
}
