<?php

namespace App\Repositories\Mst;

use Nexus\Core\Support\CustomCollection;
use NexusVip\Models\MstVipLevelReward;
use NexusVip\Repositories\VipLevelRewardRepositoryInterface;

/**
 * MstVipLevelRewardRepository
 *
 * VIPレベルアップ報酬マスターのRepository
 *
 * @extends _BaseMstRepository<MstVipLevelReward>
 */
class MstVipLevelRewardRepository extends _BaseMstRepository implements VipLevelRewardRepositoryInterface
{
    protected string $modelClass = MstVipLevelReward::class;

    /** @var list<string> id列を持たない複合主キーのマスター */
    protected array $uniqueKeys = ['vip_level', 'content_type', 'content_mst_id'];

    /**
     * VIPレベルに対応する報酬一覧を取得
     * sort_order昇順でソート
     *
     * @return CustomCollection<array-key, MstVipLevelReward>
     */
    public function selectByVipLevel(int $vipLevel): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('vip_level', $vipLevel)
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * 有効な報酬のみを取得
     * sort_order昇順でソート
     *
     * @return CustomCollection<array-key, MstVipLevelReward>
     */
    public function selectActiveByVipLevel(int $vipLevel): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('vip_level', $vipLevel)
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * 複数のVIPレベルに対応する報酬を取得
     * レベルアップ時に複数レベル分の報酬をまとめて取得する用
     *
     * @param  int  $fromLevel  開始VIPレベル（含まない）
     * @param  int  $toLevel  終了VIPレベル（含む）
     * @return CustomCollection<array-key, MstVipLevelReward>
     */
    public function selectActiveByLevelRange(int $fromLevel, int $toLevel): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('is_active', true)
            ->filter(function (MstVipLevelReward $reward) use ($fromLevel, $toLevel) {
                $level = $reward->getVipLevel();

                return $level > $fromLevel && $level <= $toLevel;
            })
            ->sortBy('vip_level')
            ->values();
    }

    /**
     * 全ての有効な報酬を取得
     *
     * @return CustomCollection<array-key, MstVipLevelReward>
     */
    public function selectAllActive(): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('is_active', true)
            ->sortBy(['vip_level', 'sort_order'])
            ->values();
    }
}
