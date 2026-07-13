<?php

namespace LaravelMobileBilling\Constants;

/**
 * 決済プラットフォーム関連の定数定義
 * 
 * プラットフォーム種別、商品タイプの定数を管理
 */
class BillingConst
{
    /**
     * 決済プラットフォーム
     */
    const PLATFORM_APP_STORE = 'AppStore';
    const PLATFORM_GOOGLE_PLAY = 'GooglePlay';
    const PLATFORM_PAYPAL = 'PayPal';
    const PLATFORM_STRIPE = 'Stripe';

    /**
     * プラットフォーム商品種別
     */
    const PRODUCT_TYPE_CONSUMABLE = 'Consumable';
    const PRODUCT_TYPE_NON_CONSUMABLE = 'NonConsumable';
    const PRODUCT_TYPE_SUBSCRIPTION = 'Subscription';

    /**
     * レシート検証ステータス
     */
    const RECEIPT_STATUS_VERIFIED = 'verified';
    const RECEIPT_STATUS_FAILED = 'failed';
    const RECEIPT_STATUS_PENDING = 'pending';
    const RECEIPT_STATUS_REFUNDED = 'refunded';

    /**
     * サブスクリプション状態
     */
    const SUBSCRIPTION_STATE_ACTIVE = 'active';
    const SUBSCRIPTION_STATE_EXPIRED = 'expired';
    const SUBSCRIPTION_STATE_CANCELLED = 'cancelled';
    const SUBSCRIPTION_STATE_GRACE_PERIOD = 'grace_period';

    /**
     * 全プラットフォームの配列を取得
     * 
     * @return array
     */
    public static function getAllPlatforms(): array
    {
        return [
            self::PLATFORM_APP_STORE,
            self::PLATFORM_GOOGLE_PLAY,
            self::PLATFORM_PAYPAL,
            self::PLATFORM_STRIPE,
        ];
    }

    /**
     * 全商品タイプの配列を取得
     * 
     * @return array
     */
    public static function getAllProductTypes(): array
    {
        return [
            self::PRODUCT_TYPE_CONSUMABLE,
            self::PRODUCT_TYPE_NON_CONSUMABLE,
            self::PRODUCT_TYPE_SUBSCRIPTION,
        ];
    }

    /**
     * プラットフォームが有効かチェック
     * 
     * @param string $platform
     * @return bool
     */
    public static function isValidPlatform(string $platform): bool
    {
        return in_array($platform, self::getAllPlatforms(), true);
    }

    /**
     * 商品タイプが有効かチェック
     * 
     * @param string $productType
     * @return bool
     */
    public static function isValidProductType(string $productType): bool
    {
        return in_array($productType, self::getAllProductTypes(), true);
    }
}
