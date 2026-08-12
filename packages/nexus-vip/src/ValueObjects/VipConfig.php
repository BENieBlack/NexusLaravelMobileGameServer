<?php

namespace NexusVip\ValueObjects;

/**
 * VIP設定 Value Object
 *
 * VIPシステムの設定値を保持する不変オブジェクト
 * Package層でLaravelに依存しないよう、設定の読み込みはApplication層で行う
 */
final class VipConfig
{
    public function __construct(
        private readonly bool $enablePointLog = true,
        private readonly bool $enableLevelUpEvent = true,
        private readonly bool $staminaBonusEnabled = true,
        private readonly bool $shopDiscountEnabled = true,
        private readonly bool $gachaDiscountEnabled = true,
        private readonly bool $dailyDiamondEnabled = true,
    ) {}

    /**
     * VIPポイントの変動ログを記録するか
     */
    public function isPointLogEnabled(): bool
    {
        return $this->enablePointLog;
    }

    /**
     * レベルアップイベントを発火するか
     */
    public function isLevelUpEventEnabled(): bool
    {
        return $this->enableLevelUpEvent;
    }

    /**
     * スタミナ上限ボーナスを有効にするか
     */
    public function isStaminaBonusEnabled(): bool
    {
        return $this->staminaBonusEnabled;
    }

    /**
     * ショップ割引を有効にするか
     */
    public function isShopDiscountEnabled(): bool
    {
        return $this->shopDiscountEnabled;
    }

    /**
     * ガチャ割引を有効にするか
     */
    public function isGachaDiscountEnabled(): bool
    {
        return $this->gachaDiscountEnabled;
    }

    /**
     * デイリーダイヤモンドボーナスを有効にするか
     */
    public function isDailyDiamondEnabled(): bool
    {
        return $this->dailyDiamondEnabled;
    }

    /**
     * 値が等しいか
     */
    public function equals(self $other): bool
    {
        return $this->enablePointLog === $other->enablePointLog
            && $this->enableLevelUpEvent === $other->enableLevelUpEvent
            && $this->staminaBonusEnabled === $other->staminaBonusEnabled
            && $this->shopDiscountEnabled === $other->shopDiscountEnabled
            && $this->gachaDiscountEnabled === $other->gachaDiscountEnabled
            && $this->dailyDiamondEnabled === $other->dailyDiamondEnabled;
    }
}
