<?php

namespace NexusVip\Services;

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
        protected VipLevelService $vipLevelService
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
        if (!config('vip.benefits_enabled.stamina_bonus', true)) {
            return $baseMaxStamina;
        }

        $benefits = $this->vipLevelService->getBenefits($vipLevel);
        return $baseMaxStamina + $benefits->maxStaminaBonus;
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
        if (!config('vip.benefits_enabled.shop_discount', true)) {
            return $basePrice;
        }

        $benefits = $this->vipLevelService->getBenefits($vipLevel);
        $discount = $basePrice * $benefits->shopDiscountRate;
        
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
        if (!config('vip.benefits_enabled.gacha_discount', true)) {
            return $basePrice;
        }

        $benefits = $this->vipLevelService->getBenefits($vipLevel);
        $discount = $basePrice * $benefits->gachaDiscountRate;
        
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
        if (!config('vip.benefits_enabled.daily_diamond', true)) {
            return 0;
        }

        $benefits = $this->vipLevelService->getBenefits($vipLevel);
        return $benefits->dailyDiamondBonus;
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
