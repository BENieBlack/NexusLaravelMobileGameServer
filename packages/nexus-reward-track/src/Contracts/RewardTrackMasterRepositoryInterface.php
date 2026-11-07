<?php

namespace NexusRewardTrack\Contracts;

use NexusRewardTrack\DataTransferObjects\RewardTrackContentMaster;
use NexusRewardTrack\DataTransferObjects\RewardTrackLineMaster;
use NexusRewardTrack\DataTransferObjects\RewardTrackMaster;
use NexusRewardTrack\DataTransferObjects\RewardTrackMilestoneMaster;

/**
 * マスターデータRepositoryインターフェース
 * mst_reward_track / mst_reward_track_line / mst_reward_track_milestone / mst_reward_track_content
 */
interface RewardTrackMasterRepositoryInterface
{
    /**
     * 現在アクティブなトラック一覧を取得する（end_at 未超過）
     *
     * @return list<RewardTrackMaster>
     */
    public function selectActiveTracks(): array;

    /**
     * IDでトラックを取得する
     *
     * @return RewardTrackMaster|null
     */
    public function selectTrackById(string $trackId): ?RewardTrackMaster;

    /**
     * トラックのライン一覧を取得する（sort_order昇順）
     *
     * @return list<RewardTrackLineMaster>
     */
    public function selectLinesByTrackId(string $trackId): array;

    /**
     * トラックのマイルストーン一覧を取得する（required_progress昇順）
     *
     * @return list<RewardTrackMilestoneMaster>
     */
    public function selectMilestonesByTrackId(string $trackId): array;

    /**
     * マイルストーンの報酬コンテンツを取得する（ライン×コンテンツ）
     *
     * @param  array<string>  $milestoneIds
     * @return list<RewardTrackContentMaster>
     */
    public function selectContentsByMilestoneIds(array $milestoneIds): array;

    /**
     * トラックの無料ラインIDを取得する
     */
    public function selectFreeLineId(string $trackId): ?string;
}
