<?php

namespace NexusVip\DTOs;

/**
 * VIP設定 Value Object
 * 
 * VIPシステムの設定値を保持する不変オブジェクト
 * Package層でLaravelに依存しないよう、fromConfig()は削除
 */
class VipConfig
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
}
