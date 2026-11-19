<?php

namespace App\Adapters\RewardTrack;

use NexusRewardTrack\DataTransferObjects\RewardTrackMilestone;

/**
 * RewardTrackMilestoneAdapter
 *
 * trx_reward_track_milestone 1 行を DTO（RewardTrackMilestone）へ変換するアダプター。
 * 対応する Model を持たないテーブルをパッケージ契約の Repository（*RepositoryAdapter）が
 * DB::table で読むため、入力は Model ではなく行の連想配列。変換は Repository 側に書かずここへ集める（ConverterLocationTest）。
 */
class RewardTrackMilestoneAdapter
{
    /**
     * DB の行（連想配列）から DTO に変換する
     *
     * @param  array<string, mixed>  $row
     */
    public static function toDto(array $row): RewardTrackMilestone
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
