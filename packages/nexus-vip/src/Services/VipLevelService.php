<?php

namespace NexusVip\Services;

use NexusVip\ValueObjects\VipBenefit;
use NexusVip\Exceptions\VipLevelNotFoundException;
use NexusVip\Models\MstVipLevel;
use NexusVip\Repositories\VipLevelRepositoryInterface;

/**
 * VIPレベルサービス
 *
 * VIPレベルの判定と特典情報の取得を担当
 */
class VipLevelService
{
    public function __construct(
        protected VipLevelRepositoryInterface $vipLevelRepository
    ) {}

    /**
     * 累積ポイントからVIPレベルを計算
     *
     * @param  int  $totalPoints  累積VIPポイント
     * @return int VIPレベル
     */
    public function calculateLevel(int $totalPoints): int
    {
        $vipLevel = $this->vipLevelRepository->selectMaxLevelByPoints($totalPoints);

        return $vipLevel->getLevel();
    }

    /**
     * 次のレベルまでの必要ポイントを取得
     *
     * @param  int  $currentLevel  現在のVIPレベル
     * @param  int  $currentPoint  現在の累積VIPポイント
     * @return int|null 次レベルまでのポイント（最高レベルの場合null）
     */
    public function calcPointsToNextLevel(int $currentLevel, int $currentPoint): ?int
    {
        $nextLevel = $this->vipLevelRepository->selectByLevel($currentLevel + 1);

        if ($nextLevel === null) {
            return null; // 最高レベル
        }

        $pointsNeeded = $nextLevel->getRequiredPoint() - $currentPoint;

        return max(0, $pointsNeeded);
    }

    /**
     * VIPレベルの特典情報を取得
     *
     * @param  int  $level  VIPレベル
     *
     * @throws VipLevelNotFoundException
     */
    public function findBenefits(int $level): VipBenefit
    {
        $vipLevel = $this->vipLevelRepository->selectByLevel($level);

        if ($vipLevel === null) {
            throw new VipLevelNotFoundException("VIP level {$level} not found");
        }

        return new VipBenefit(
            maxStaminaBonus: $vipLevel->getMaxStaminaBonus(),
            dailyDiamondBonus: $vipLevel->calcDailyDiamondBonus(),
            shopDiscountRate: $vipLevel->getShopDiscountRate(),
            gachaDiscountRate: $vipLevel->getGachaDiscountRate(),
        );
    }

    /**
     * VIPレベルマスターデータを取得
     *
     * @param  int  $level  VIPレベル
     *
     * @throws VipLevelNotFoundException
     */
    public function findVipLevelMaster(int $level): MstVipLevel
    {
        $vipLevel = $this->vipLevelRepository->selectByLevel($level);

        if ($vipLevel === null) {
            throw new VipLevelNotFoundException("VIP level {$level} not found");
        }

        return $vipLevel;
    }

    /**
     * 全VIPレベルのリストを取得
     *
     * @return array<int, array>
     */
    public function findAllLevels(): array
    {
        $levels = $this->vipLevelRepository->selectAllLevels();

        return $levels->map(function (MstVipLevel $level) {
            return [
                'level' => $level->getLevel(),
                'required_point' => $level->getRequiredPoint(),
                'benefits' => [
                    'max_stamina_bonus' => $level->getMaxStaminaBonus(),
                    'daily_diamond_bonus' => $level->calcDailyDiamondBonus(),
                    'shop_discount_rate' => $level->getShopDiscountRate(),
                    'gacha_discount_rate' => $level->getGachaDiscountRate(),
                ],
                'display_badge_url' => $level->getDisplayBadgeUrl(),
            ];
        })->values()->toArray();
    }
}
