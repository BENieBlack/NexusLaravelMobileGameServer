<?php

namespace NexusVip\DTOs;

/**
 * VIP特典情報DTO
 */
class VipBenefitDto
{
    public function __construct(
        public readonly int $maxStaminaBonus,
        public readonly int $dailyDiamondBonus,
        public readonly float $shopDiscountRate,
        public readonly float $gachaDiscountRate,
    ) {
    }

    /**
     * 配列に変換
     *
     * @return array
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
