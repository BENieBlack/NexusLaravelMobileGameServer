<?php

namespace NexusRewardTrack\Repositories;

use NexusRewardTrack\DataTransferObjects\RewardTrack;

/**
 * プレイヤーの進捗Repositoryインターフェース
 */
interface RewardTrackRepositoryInterface
{
    /**
     * プレイヤーのトラック進捗を取得する
     */
    public function findByPlayerAndTrack(int $sysPlayerId, string $mstRewardTrackId, string $connectionName): ?RewardTrack;

    /**
     * プレイヤーの全トラック進捗を取得する
     *
     * @return array<RewardTrack>
     */
    public function findAllByPlayer(int $sysPlayerId, string $connectionName): array;

    /**
     * 進捗を作成または更新する
     */
    public function upsertProgress(int $sysPlayerId, string $mstRewardTrackId, int $progress, string $connectionName): RewardTrack;

    /**
     * 進捗値を加算する（現在値 + delta）
     */
    public function addProgress(int $sysPlayerId, string $mstRewardTrackId, int $delta, string $connectionName): RewardTrack;
}
