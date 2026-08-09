<?php

namespace NexusVip\DTOs;

/**
 * VIP設定DTO
 * 
 * VIPシステムの設定値を保持
 */
class VipConfigDto
{
    public function __construct(
        public readonly bool $enablePointLog = true,
        public readonly bool $enableLevelUpEvent = true,
        public readonly bool $staminaBonusEnabled = true,
        public readonly bool $shopDiscountEnabled = true,
        public readonly bool $gachaDiscountEnabled = true,
        public readonly bool $dailyDiamondEnabled = true,
    ) {
    }

    /**
     * 配列から生成
     *
     * @param array $config
     * @return self
     */
    public static function fromArray(array $config): self
    {
        return new self(
            enablePointLog: $config['enable_point_log'] ?? true,
            enableLevelUpEvent: $config['enable_level_up_event'] ?? true,
            staminaBonusEnabled: $config['benefits_enabled']['stamina_bonus'] ?? true,
            shopDiscountEnabled: $config['benefits_enabled']['shop_discount'] ?? true,
            gachaDiscountEnabled: $config['benefits_enabled']['gacha_discount'] ?? true,
            dailyDiamondEnabled: $config['benefits_enabled']['daily_diamond'] ?? true,
        );
    }

    /**
     * Laravel config()から生成
     *
     * @return self
     */
    public static function fromConfig(): self
    {
        return self::fromArray([
            'enable_point_log' => config('vip.enable_point_log', true),
            'enable_level_up_event' => config('vip.enable_level_up_event', true),
            'benefits_enabled' => [
                'stamina_bonus' => config('vip.benefits_enabled.stamina_bonus', true),
                'shop_discount' => config('vip.benefits_enabled.shop_discount', true),
                'gacha_discount' => config('vip.benefits_enabled.gacha_discount', true),
                'daily_diamond' => config('vip.benefits_enabled.daily_diamond', true),
            ],
        ]);
    }
}
