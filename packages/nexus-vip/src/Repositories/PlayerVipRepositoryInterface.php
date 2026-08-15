<?php

namespace NexusVip\Repositories;

use NexusVip\DataTransferObjects\PlayerVip;

/**
 * プレイヤーVIP情報Repositoryインターフェース
 */
interface PlayerVipRepositoryInterface
{
    /**
     * プレイヤーIDでVIP情報を検索
     */
    public function selectVipInfoById(int $sysPlayerId): ?PlayerVip;

    /**
     * プレイヤーVIP情報を保存（Unit of Workパターンで使用）
     */
    public function persistVipInfo(PlayerVip $playerVip): void;

    /**
     * VIPポイントの範囲でプレイヤーを検索
     *
     * @param  int  $minPoint  最小VIPポイント
     * @param  int|null  $maxPoint  最大VIPポイント（nullの場合は上限なし）
     * @param  int  $limit  取得件数
     * @return array<PlayerVip>
     */
    public function selectByPointRange(int $minPoint, ?int $maxPoint = null, int $limit = 100): array;
}
