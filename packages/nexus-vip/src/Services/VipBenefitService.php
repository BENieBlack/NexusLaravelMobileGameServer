<?php

namespace NexusVip\Services;

use NexusVip\ValueObjects\VipConfig;

/**
 * VIP特典サービス
 * 
 * VIP特典の適用を担当
 * 
 * Note: VIPレベルは vip_point から動的に計算される
 * クライアント側でも mst_vip_level を参照して判定可能
 */
class VipBenefitService
{
    public function __construct(
        protected VipLevelService $vipLevelService,
        protected VipConfig $config,
    ) {
    }

    /**
     * スタミナ上限にVIPボーナスを適用
     *
     * @param int $baseMaxStamina 基本スタミナ上限
     * @param int $vipLevel VIPレベル（vip_pointから計算済み）
     * @return int VIPボーナス適用後のスタミナ上限
     */
    public function applyStaminaBonus(int $baseMaxStamina, int $vipLevel): int
    {
        if (!$this->config->staminaBonusEnabled) {
            return $baseMaxStamina;
        }

        $benefits = $this->vipLevelService->getBenefits($vipLevel);
        return $baseMaxStamina + $benefits->getMaxStaminaBonus();
    }

    /**
     * ショップ価格にVIP割引を適用
     *
     * @param int $basePrice 基本価格
     * @param int $vipLevel VIPレベル（vip_pointから計算済み）
     * @return int VIP割引適用後の価格
     */
    public function applyShopDiscount(int $basePrice, int $vipLevel): int
    {
        if (!$this->config->shopDiscountEnabled) {
            return $basePrice;
        }

        $benefits = $this->vipLevelService->getBenefits($vipLevel);
        $discount = $basePrice * $benefits->getShopDiscountRate();
        
        // 最低価格は1
        return max(1, (int) floor($basePrice - $discount));
    }

    /**
     * ガチャ価格にVIP割引を適用
     *
     * @param int $basePrice 基本価格
     * @param int $vipLevel VIPレベル（vip_pointから計算済み）
     * @return int VIP割引適用後の価格
     */
    public function applyGachaDiscount(int $basePrice, int $vipLevel): int
    {
        if (!$this->config->gachaDiscountEnabled) {
            return $basePrice;
        }

        $benefits = $this->vipLevelService->getBenefits($vipLevel);
        $discount = $basePrice * $benefits->getGachaDiscountRate();
        
        // 最低価格は1
        return max(1, (int) floor($basePrice - $discount));
    }

    /**
     * デイリーダイヤモンドボーナスを取得
     *
     * @param int $vipLevel VIPレベル（vip_pointから計算済み）
     * @return int デイリーダイヤモンドボーナス
     */
    public function getDailyDiamondBonus(int $vipLevel): int
    {
        if (!$this->config->dailyDiamondEnabled) {
            return 0;
        }

        $benefits = $this->vipLevelService->getBenefits($vipLevel);
        return $benefits->getDailyDiamondBonus();
    }

    /**
     * 価格に割引を適用（汎用）
     *
     * @param int $basePrice 基本価格
     * @param float $discountRate 割引率（0.0 ~ 1.0）
     * @return int 割引適用後の価格
     */
    public function applyDiscount(int $basePrice, float $discountRate): int
    {
        $discount = $basePrice * $discountRate;
        return max(1, (int) floor($basePrice - $discount));
    }
}
