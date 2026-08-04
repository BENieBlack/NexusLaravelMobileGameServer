<?php

namespace NexusVip\Repositories;

use Illuminate\Support\Collection;
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
     * @return Collection<MstVipLevelReward>
     */
    public function findByVipLevel(int $vipLevel): Collection;

    /**
     * 有効な報酬のみを取得
     *
     * @param int $vipLevel
     * @return Collection<MstVipLevelReward>
     */
    public function findActiveByVipLevel(int $vipLevel): Collection;
}
