<?php

namespace App\Adapters\RewardTrack;

use NexusRewardTrack\DataTransferObjects\RewardTrackLine;

/**
 * RewardTrackLineAdapter
 *
 * trx_reward_track_line 1 行を DTO（RewardTrackLine）へ変換するアダプター。
 * 対応する Model を持たないテーブルをパッケージ契約の Repository（*RepositoryAdapter）が
 * DB::table で読むため、入力は Model ではなく行の連想配列。変換は Repository 側に書かずここへ集める（ConverterLocationTest）。
 */
class RewardTrackLineAdapter
{
    /**
     * DB の行（連想配列）から DTO に変換する
     *
     * @param  array<string, mixed>  $row
     */
    public static function toDto(array $row): RewardTrackLine
    {
        return new RewardTrackLine(
            id: (int) $row['id'],
            sysPlayerId: (int) $row['sys_player_id'],
            mstRewardTrackLineId: $row['mst_reward_track_line_id'],
            mstInAppPurchaseId: (int) $row['mst_in_app_purchase_id'],
            purchasedAt: $row['purchased_at'],
            isDelete: (bool) $row['is_delete'],
            createdAt: $row['created_at'] ?? null,
            updatedAt: $row['updated_at'] ?? null,
        );
    }
}
