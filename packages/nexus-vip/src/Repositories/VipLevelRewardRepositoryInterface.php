<?php

namespace NexusVip\Repositories;

use NexusPersistence\Support\CustomCollection;
use NexusVip\Models\MstVipLevelReward;

/**
 * VIPレベル報酬リポジトリインターフェース
 */
interface VipLevelRewardRepositoryInterface
{
    /**
     * VIPレベルに対応する報酬一覧を取得
     *
     * @param int $vipLevel
     * @return CustomCollection<MstVipLevelReward>
     */
    public function findByVipLevel(int $vipLevel): CustomCollection;

    /**
     * 有効な報酬のみを取得
     *
     * @param int $vipLevel
     * @return CustomCollection<MstVipLevelReward>
     */
    public function findActiveByVipLevel(int $vipLevel): CustomCollection;
}
