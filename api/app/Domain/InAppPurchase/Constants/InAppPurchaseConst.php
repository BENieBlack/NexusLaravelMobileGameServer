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
    const TYPE_DIAMOND = 'diamond';

    const TYPE_PACK = 'pack';

    const TYPE_PASS = 'pass';

    /**
     * 購入制限リセット
     */
    const PURCHASE_LIMIT_RESET_NONE = 'none';

    const PURCHASE_LIMIT_RESET_DAILY = 'daily';

    const PURCHASE_LIMIT_RESET_WEEKLY = 'weekly';

    const PURCHASE_LIMIT_RESET_MONTHLY = 'monthly';

    /**
     * コンテンツタイプ
     */
    const CONTENT_TYPE_ITEM = 'item';

    const CONTENT_TYPE_UNIT = 'unit';

    const CONTENT_TYPE_FREE_DIAMOND = 'free_diamond';

    /**
     * 効果タイプ
     */
    const EFFECT_TYPE_IDLE_REWARD_MULTIPLIER = 'idle_reward_multiplier';

    const EFFECT_TYPE_AD_SKIP = 'ad_skip';

    const EFFECT_TYPE_EXP_BOOST = 'exp_boost';

    const EFFECT_TYPE_GOLD_BOOST = 'gold_boost';

    const EFFECT_TYPE_DAILY_MISSION_BONUS = 'daily_mission_bonus';

    /**
     * 全課金商品タイプの配列を取得
     *
     * @return array<int, string>
     */
    public static function allTypes(): array
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
     * @return array<int, string>
     */
    public static function allPurchaseLimitResets(): array
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
     * @return array<int, string>
     */
    public static function allContentTypes(): array
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
     * @return array<int, string>
     */
    public static function allEffectTypes(): array
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
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::allTypes(), true);
    }

    /**
     * 購入制限リセットが有効かチェック
     */
    public static function isValidPurchaseLimitReset(string $reset): bool
    {
        return in_array($reset, self::allPurchaseLimitResets(), true);
    }

    /**
     * コンテンツタイプが有効かチェック
     */
    public static function isValidContentType(string $contentType): bool
    {
        return in_array($contentType, self::allContentTypes(), true);
    }

    /**
     * 効果タイプが有効かチェック
     */
    public static function isValidEffectType(string $effectType): bool
    {
        return in_array($effectType, self::allEffectTypes(), true);
    }
}
