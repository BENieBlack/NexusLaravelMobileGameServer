<?php

namespace NexusVip\Services;

use NexusVip\ValueObjects\VipReward;
use NexusVip\Repositories\VipLevelRewardRepositoryInterface;

/**
 * VIP報酬サービス
 *
 * VIPレベルアップ時の報酬取得を担当
 */
class VipRewardService
{
    public function __construct(
        protected VipLevelRewardRepositoryInterface $vipLevelRewardRepository
    ) {}

    /**
     * VIPレベルに対応する報酬一覧を取得
     *
     * @return array<VipReward>
     */
    public function findRewardsByLevel(int $vipLevel): array
    {
        $rewards = $this->vipLevelRewardRepository->selectActiveByVipLevel($vipLevel);

        return $rewards->map(function ($reward) {
            return new VipReward(
                contentType: $reward->getContentType(),
                contentMstId: $reward->getContentMstId(),
                contentOption: $reward->getContentOption(),
                contentQuantity: $reward->getContentQuantity(),
                amount: $reward->getAmount(),
                isPaid: $reward->getIsPaid(),
            );
        })->values()->toArray();
    }

    /**
     * 報酬があるかチェック
     */
    public function hasRewards(int $vipLevel): bool
    {
        $rewards = $this->vipLevelRewardRepository->selectActiveByVipLevel($vipLevel);

        return $rewards->isNotEmpty();
    }

    /**
     * 報酬を配列形式で取得（API レスポンス用）
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildRewardsArray(int $vipLevel): array
    {
        $rewards = $this->findRewardsByLevel($vipLevel);

        return array_map(function (VipReward $reward) {
            return $reward->toArray();
        }, $rewards);
    }
}
