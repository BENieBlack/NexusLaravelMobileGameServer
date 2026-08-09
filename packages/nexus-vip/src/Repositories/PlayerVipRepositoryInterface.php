<?php

namespace NexusVip\Repositories;

use NexusVip\DTOs\PlayerVipDto;

/**
 * プレイヤーVIP情報Repositoryインターフェース
 */
interface PlayerVipRepositoryInterface
{
    /**
     * プレイヤーIDでVIP情報を検索
     *
     * @param int $sysPlayerId
     * @return PlayerVipDto|null
     */
    public function findVipInfoById(int $sysPlayerId): ?PlayerVipDto;

    /**
     * プレイヤーVIP情報を保存（Unit of Workパターンで使用）
     *
     * @param PlayerVipDto $playerVipDto
     * @return void
     */
    public function saveVipInfo(PlayerVipDto $playerVipDto): void;

    /**
     * VIPポイントの範囲でプレイヤーを検索
     *
     * @param int $minPoint 最小VIPポイント
     * @param int|null $maxPoint 最大VIPポイント（nullの場合は上限なし）
     * @param int $limit 取得件数
     * @return array<PlayerVipDto>
     */
    public function findByPointRange(int $minPoint, ?int $maxPoint = null, int $limit = 100): array;
}
