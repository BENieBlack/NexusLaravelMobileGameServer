<?php

namespace LaravelMobileBilling\Services;

use LaravelMobileBilling\Constants\BillingConst;
use LaravelMobileBilling\Contracts\BillingPlatformInterface;
use InvalidArgumentException;

/**
 * Billing プラットフォームファクトリ
 * 
 * プラットフォーム名に応じて適切なBillingServiceを返す
 */
class BillingPlatformFactory
{
    public function __construct(
        private readonly AppStoreBillingService $appStoreService,
        private readonly GooglePlayBillingService $googlePlayService,
    ) {}

    /**
     * プラットフォームに対応するサービスを取得
     * 
     * @param string $billingPlatform プラットフォーム名（BillingConst::PLATFORM_*）
     * @return BillingPlatformInterface
     * @throws InvalidArgumentException サポートされていないプラットフォームの場合
     */
    public function create(string $billingPlatform): BillingPlatformInterface
    {
        return match ($billingPlatform) {
            BillingConst::PLATFORM_APP_STORE => $this->appStoreService,
            BillingConst::PLATFORM_GOOGLE_PLAY => $this->googlePlayService,
            default => throw new InvalidArgumentException(
                "Unsupported billing platform: {$billingPlatform}"
            ),
        };
    }

    /**
     * サポートされているプラットフォームかチェック
     * 
     * @param string $billingPlatform
     * @return bool
     */
    public function isSupported(string $billingPlatform): bool
    {
        return in_array($billingPlatform, [
            BillingConst::PLATFORM_APP_STORE,
            BillingConst::PLATFORM_GOOGLE_PLAY,
        ], true);
    }
}
