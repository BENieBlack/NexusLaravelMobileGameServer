<?php

namespace NexusVip\Services;

use NexusVip\DTOs\VipRewardDto;
use NexusVip\Repositories\VipLevelRewardRepositoryInterface;

/**
 * VIP報酬サービス
 * 
 * VIPレベルアップ時の報酬取得を担当
 */
class RewardService
{
    public function __construct(
        protected VipLevelRewardRepositoryInterface $vipLevelRewardRepository
    ) {
    }

    /**
     * VIPレベルに対応する報酬一覧を取得
     *
     * @param int $vipLevel
     * @return array<VipRewardDto>
     */
    public function getRewardsByLevel(int $vipLevel): array
    {
        $rewards = $this->vipLevelRewardRepository->findActiveByVipLevel($vipLevel);

        return $rewards->map(function ($reward) {
            return new VipRewardDto(
                contentType: $reward->getContentType(),
                contentId: $reward->getContentId(),
                contentOption: $reward->getContentOption(),
                contentQuantity: $reward->getContentQuantity(),
                amount: $reward->getAmount(),
                isPaid: $reward->getIsPaid(),
            );
        })->values()->toArray();
    }

    /**
     * 報酬があるかチェック
     *
     * @param int $vipLevel
     * @return bool
     */
    public function hasRewards(int $vipLevel): bool
    {
        $rewards = $this->vipLevelRewardRepository->findActiveByVipLevel($vipLevel);
        return $rewards->isNotEmpty();
    }

    /**
     * 報酬を配列形式で取得（API レスポンス用）
     *
     * @param int $vipLevel
     * @return array
     */
    public function getRewardsArray(int $vipLevel): array
    {
        $rewards = $this->getRewardsByLevel($vipLevel);

        return array_map(function (VipRewardDto $reward) {
            return $reward->toArray();
        }, $rewards);
    }
}
