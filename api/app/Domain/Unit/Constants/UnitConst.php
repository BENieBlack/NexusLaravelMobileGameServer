<?php

namespace App\Domain\Unit\Constants;

use App\Domain\Common\Constants\ElementType;
use App\Domain\Common\Constants\RarityType;

/**
 * ユニット関連の定数定義
 *
 * ユニットのタイプ定数を管理
 * 属性・レアリティは共通定数（App\Domain\Common\Constants）を使用
 */
class UnitConst
{
    /**
     * ユニットタイプ
     */
    const TYPE_ATTACK = 'Attack';

    const TYPE_DEFENSE = 'Defense';

    const TYPE_SUPPORT = 'Support';

    /**
     * ユニット属性（共通定数を使用）
     *
     * @deprecated Use App\Domain\Common\Constants\ElementType instead
     */
    const ELEMENT_FIRE = ElementType::FIRE;

    const ELEMENT_WATER = ElementType::WATER;

    const ELEMENT_WIND = ElementType::WIND;

    const ELEMENT_EARTH = ElementType::EARTH;

    const ELEMENT_LIGHT = ElementType::LIGHT;

    const ELEMENT_DARK = ElementType::DARK;

    /**
     * ユニットレアリティ（共通定数を使用）
     *
     * @deprecated Use App\Domain\Common\Constants\RarityType instead
     */
    const RARITY_UR = RarityType::UR;

    const RARITY_SSR = RarityType::SSR;

    const RARITY_SR = RarityType::SR;

    const RARITY_R = RarityType::R;

    const RARITY_UC = RarityType::UC;

    const RARITY_C = RarityType::C;

    /**
     * 全タイプの配列を取得
     */
    public static function getAllTypes(): array
    {
        return [
            self::TYPE_ATTACK,
            self::TYPE_DEFENSE,
            self::TYPE_SUPPORT,
        ];
    }

    /**
     * 全属性の配列を取得
     *
     * @deprecated Use ElementType::getAll() instead
     */
    public static function getAllElements(): array
    {
        return ElementType::getAll();
    }

    /**
     * 全レアリティの配列を取得
     *
     * @deprecated Use RarityType::getAll() instead
     */
    public static function getAllRarities(): array
    {
        return RarityType::getAll();
    }

    /**
     * タイプが有効かチェック
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::getAllTypes(), true);
    }

    /**
     * 属性が有効かチェック
     *
     * @deprecated Use ElementType::isValid() instead
     */
    public static function isValidElement(string $element): bool
    {
        return ElementType::isValid($element);
    }

    /**
     * レアリティが有効かチェック
     *
     * @deprecated Use RarityType::isValid() instead
     */
    public static function isValidRarity(string $rarity): bool
    {
        return RarityType::isValid($rarity);
    }
}
