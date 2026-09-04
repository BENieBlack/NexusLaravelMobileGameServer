<?php

namespace NexusRewardTrack\Repositories;

use NexusRewardTrack\DataTransferObjects\RewardTrackMilestone;

/**
 * プレイヤーの受け取り済みマイルストーンRepositoryインターフェース
 */
interface RewardTrackMilestoneRepositoryInterface
{
    /**
     * プレイヤーのトラック内受け取り済みマイルストーンを取得する
     *
     * @return array<RewardTrackMilestone>
     */
    public function findByPlayerAndTrack(int $sysPlayerId, string $mstRewardTrackId, string $connectionName): array;

    /**
     * 特定のマイルストーン×ラインを受け取り済みか確認する
     */
    public function hasReceived(int $sysPlayerId, string $mstRewardTrackMilestoneId, string $mstRewardTrackLineId, string $connectionName): bool;

    /**
     * 受け取り済みとして記録する
     */
    public function insertReceipt(int $sysPlayerId, string $mstRewardTrackMilestoneId, string $mstRewardTrackLineId, string $receivedAt, string $connectionName): RewardTrackMilestone;

    /**
     * プレイヤーのマイルストーン受け取り済みIDセットを取得する
     * key: "{mst_reward_track_milestone_id}:{mst_reward_track_line_id}" → true
     *
     * @return array<string, bool>
     */
    public function findReceivedKeySet(int $sysPlayerId, string $mstRewardTrackId, string $connectionName): array;
}
