<?php

namespace NexusVip\Repositories;

use NexusPersistence\Support\CustomCollection;
use NexusVip\Models\MstVipLevel;

/**
 * VIPレベルマスターRepositoryインターフェース
 */
interface VipLevelRepositoryInterface
{
    /**
     * 全VIPレベルを取得（キャッシュから）
     *
     * @return CustomCollection<MstVipLevel>
     */
    public function getAllLevels(): CustomCollection;

    /**
     * VIPレベル番号で検索
     *
     * @param int $level VIPレベル
     * @return MstVipLevel|null
     */
    public function findByLevel(int $level): ?MstVipLevel;

    /**
     * VIPレベルIDで検索
     *
     * @param string $id VIPレベルID (例: "vip_5")
     * @return MstVipLevel|null
     */
    public function findById(string $id): ?MstVipLevel;

    /**
     * 必要ポイント以下の最大レベルを取得
     *
     * @param int $points 累積VIPポイント
     * @return MstVipLevel
     */
    public function findMaxLevelByPoints(int $points): MstVipLevel;
}
