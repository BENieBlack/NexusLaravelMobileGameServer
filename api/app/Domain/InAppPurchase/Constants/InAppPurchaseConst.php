<?php

namespace App\Domain\InAppPurchase\Constants;

/**
 * アプリ内課金関連の定数定義
 * 
 * 課金商品タイプ、購入制限リセット、コンテンツタイプ、効果タイプの定数を管理
 */
class InAppPurchaseConst
{
    /**
     * 課金商品タイプ
     */
    const TYPE_DIAMOND = 'Diamond';
    const TYPE_PACK = 'Pack';
    const TYPE_PASS = 'Pass';

    /**
     * 購入制限リセット
     */
    const PURCHASE_LIMIT_RESET_NONE = 'None';
    const PURCHASE_LIMIT_RESET_DAILY = 'Daily';
    const PURCHASE_LIMIT_RESET_WEEKLY = 'Weekly';
    const PURCHASE_LIMIT_RESET_MONTHLY = 'Monthly';

    /**
     * コンテンツタイプ
     */
    const CONTENT_TYPE_ITEM = 'Item';
    const CONTENT_TYPE_UNIT = 'Unit';
    const CONTENT_TYPE_FREE_DIAMOND = 'FreeDiamond';

    /**
     * 効果タイプ
     */
    const EFFECT_TYPE_IDLE_REWARD_MULTIPLIER = 'IdleRewardMultiplier';
    const EFFECT_TYPE_AD_SKIP = 'AdSkip';
    const EFFECT_TYPE_EXP_BOOST = 'ExpBoost';
    const EFFECT_TYPE_GOLD_BOOST = 'GoldBoost';
    const EFFECT_TYPE_DAILY_MISSION_BONUS = 'DailyMissionBonus';

    /**
     * 全課金商品タイプの配列を取得
     * 
     * @return array
     */
    public static function getAllTypes(): array
    {
        return [
            self::TYPE_DIAMOND,
            self::TYPE_PACK,
            self::TYPE_PASS,
        ];
    }

    /**
     * 全購入制限リセットの配列を取得
     * 
     * @return array
     */
    public static function getAllPurchaseLimitResets(): array
    {
        return [
            self::PURCHASE_LIMIT_RESET_NONE,
            self::PURCHASE_LIMIT_RESET_DAILY,
            self::PURCHASE_LIMIT_RESET_WEEKLY,
            self::PURCHASE_LIMIT_RESET_MONTHLY,
        ];
    }

    /**
     * 全コンテンツタイプの配列を取得
     * 
     * @return array
     */
    public static function getAllContentTypes(): array
    {
        return [
            self::CONTENT_TYPE_ITEM,
            self::CONTENT_TYPE_UNIT,
            self::CONTENT_TYPE_FREE_DIAMOND,
        ];
    }

    /**
     * 全効果タイプの配列を取得
     * 
     * @return array
     */
    public static function getAllEffectTypes(): array
    {
        return [
            self::EFFECT_TYPE_IDLE_REWARD_MULTIPLIER,
            self::EFFECT_TYPE_AD_SKIP,
            self::EFFECT_TYPE_EXP_BOOST,
            self::EFFECT_TYPE_GOLD_BOOST,
            self::EFFECT_TYPE_DAILY_MISSION_BONUS,
        ];
    }

    /**
     * 課金商品タイプが有効かチェック
     * 
     * @param string $type
     * @return bool
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::getAllTypes(), true);
    }

    /**
     * 購入制限リセットが有効かチェック
     * 
     * @param string $reset
     * @return bool
     */
    public static function isValidPurchaseLimitReset(string $reset): bool
    {
        return in_array($reset, self::getAllPurchaseLimitResets(), true);
    }

    /**
     * コンテンツタイプが有効かチェック
     * 
     * @param string $contentType
     * @return bool
     */
    public static function isValidContentType(string $contentType): bool
    {
        return in_array($contentType, self::getAllContentTypes(), true);
    }

    /**
     * 効果タイプが有効かチェック
     * 
     * @param string $effectType
     * @return bool
     */
    public static function isValidEffectType(string $effectType): bool
    {
        return in_array($effectType, self::getAllEffectTypes(), true);
    }
}
