<?php

namespace NexusRewardTrack\Contracts;

/**
 * マスターデータRepositoryインターフェース
 * mst_reward_track / mst_reward_track_line / mst_reward_track_milestone / mst_reward_track_content
 */
interface RewardTrackMasterRepositoryInterface
{
    /**
     * 現在アクティブなトラック一覧を取得する（end_at 未超過）
     *
     * @return array<array{id: string, progress_type: string, start_at: string, end_at: ?string, sort_desc: int}>
     */
    public function selectActiveTracks(): array;

    /**
     * IDでトラックを取得する
     *
     * @return array{id: string, progress_type: string, start_at: string, end_at: ?string}|null
     */
    public function selectTrackById(string $trackId): ?array;

    /**
     * トラックのライン一覧を取得する（sort_order昇順）
     *
     * @return array<array{id: string, mst_reward_track_id: string, is_free: bool, mst_in_app_purchase_id: ?int, sort_order: int}>
     */
    public function selectLinesByTrackId(string $trackId): array;

    /**
     * トラックのマイルストーン一覧を取得する（required_progress昇順）
     *
     * @return array<array{id: string, mst_reward_track_id: string, required_progress: int, sort_order: int}>
     */
    public function selectMilestonesByTrackId(string $trackId): array;

    /**
     * マイルストーンの報酬コンテンツを取得する（ライン×コンテンツ）
     *
     * @param  array<string>  $milestoneIds
     * @return array<array{mst_reward_track_milestone_id: string, mst_reward_track_line_id: string, content_type: string, content_mst_id: string, content_option: ?array, content_quantity: int, amount: int, sort_order: int}>
     */
    public function selectContentsByMilestoneIds(array $milestoneIds): array;

    /**
     * トラックの無料ラインIDを取得する
     */
    public function selectFreeLineId(string $trackId): ?string;
}
