<?php

namespace NexusVip\Repositories;

use App\Models\Sys\SysPlayer;

/**
 * プレイヤーVIP情報Repositoryインターフェース
 */
interface PlayerVipRepositoryInterface
{
    /**
     * プレイヤーIDでVIP情報を検索
     *
     * @param int $sysPlayerId
     * @return SysPlayer|null
     */
    public function findVipInfoById(int $sysPlayerId): ?SysPlayer;

    /**
     * モデルを登録（Unit of Workパターンで使用）
     *
     * @param SysPlayer $model
     * @return void
     */
    public function setModel(SysPlayer $model): void;

    /**
     * VIPレベルの範囲でプレイヤーを検索
     *
     * @param int $minLevel 最小VIPレベル
     * @param int|null $maxLevel 最大VIPレベル（nullの場合は上限なし）
     * @param int $limit 取得件数
     * @return array<SysPlayer>
     */
    public function findByLevelRange(int $minLevel, ?int $maxLevel = null, int $limit = 100): array;

    /**
     * VIPポイントの範囲でプレイヤーを検索
     *
     * @param int $minPoint 最小VIPポイント
     * @param int|null $maxPoint 最大VIPポイント（nullの場合は上限なし）
     * @param int $limit 取得件数
     * @return array<SysPlayer>
     */
    public function findByPointRange(int $minPoint, ?int $maxPoint = null, int $limit = 100): array;
}
