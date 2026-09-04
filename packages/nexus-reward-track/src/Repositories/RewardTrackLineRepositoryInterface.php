<?php

namespace NexusRewardTrack\Repositories;

use NexusRewardTrack\DataTransferObjects\RewardTrackLine;

/**
 * プレイヤーの購入済みラインRepositoryインターフェース
 */
interface RewardTrackLineRepositoryInterface
{
    /**
     * プレイヤーが所持するラインを全て取得する
     *
     * @return array<RewardTrackLine>
     */
    public function findAllByPlayer(int $sysPlayerId, string $connectionName): array;

    /**
     * プレイヤーが特定のラインを所持しているか確認する
     */
    public function hasLine(int $sysPlayerId, string $mstRewardTrackLineId, string $connectionName): bool;

    /**
     * プレイヤーがトラックの無料ラインを含む全ラインIDを取得する
     *
     * @param  array<string>  $mstRewardTrackLineIds  トラックに紐づく全ラインID
     * @return array<string>  プレイヤーが所持しているラインID
     */
    public function findOwnedLineIds(int $sysPlayerId, array $mstRewardTrackLineIds, string $connectionName): array;

    /**
     * ラインを購入済みとして登録する
     */
    public function insertLine(int $sysPlayerId, string $mstRewardTrackLineId, int $mstInAppPurchaseId, string $purchasedAt, string $connectionName): RewardTrackLine;
}
