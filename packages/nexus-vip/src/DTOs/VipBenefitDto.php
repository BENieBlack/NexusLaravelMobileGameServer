<?php

namespace NexusVip\DTOs;

/**
 * VIP特典情報DTO
 */
class VipBenefitDto
{
    public function __construct(
        private readonly int $maxStaminaBonus,
        private readonly int $dailyDiamondBonus,
        private readonly float $shopDiscountRate,
        private readonly float $gachaDiscountRate,
    ) {}

    /**
     * 最大スタミナボーナスを取得
     */
    public function getMaxStaminaBonus(): int
    {
        return $this->maxStaminaBonus;
    }

    /**
     * 毎日のダイヤモンドボーナスを取得
     */
    public function getDailyDiamondBonus(): int
    {
        return $this->dailyDiamondBonus;
    }

    /**
     * ショップ割引率を取得
     */
    public function getShopDiscountRate(): float
    {
        return $this->shopDiscountRate;
    }

    /**
     * ガチャ割引率を取得
     */
    public function getGachaDiscountRate(): float
    {
        return $this->gachaDiscountRate;
    }

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'max_stamina_bonus' => $this->maxStaminaBonus,
            'daily_diamond_bonus' => $this->dailyDiamondBonus,
            'shop_discount_rate' => $this->shopDiscountRate,
            'gacha_discount_rate' => $this->gachaDiscountRate,
        ];
    }
}
