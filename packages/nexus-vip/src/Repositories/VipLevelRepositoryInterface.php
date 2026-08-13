<?php

namespace NexusVip\Repositories;

use Nexus\Core\Support\CustomCollection;
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
     * @param  int  $level  VIPレベル
     */
    public function selectByLevel(int $level): ?MstVipLevel;

    /**
     * VIPレベルIDで検索
     *
     * @param  string  $id  VIPレベルID (例: "vip_5")
     */
    public function selectById(string $id): ?MstVipLevel;

    /**
     * 必要ポイント以下の最大レベルを取得
     *
     * @param  int  $points  累積VIPポイント
     */
    public function selectMaxLevelByPoints(int $points): MstVipLevel;
}
