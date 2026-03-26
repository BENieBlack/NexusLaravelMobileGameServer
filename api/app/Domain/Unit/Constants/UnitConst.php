<?php

namespace App\Domain\Unit\Constants;

/**
 * ユニット関連の定数定義
 * 
 * ユニットのタイプ、属性、レアリティの定数を管理
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
     * ユニット属性
     */
    const ELEMENT_FIRE = 'Fire';
    const ELEMENT_WATER = 'Water';
    const ELEMENT_WIND = 'Wind';
    const ELEMENT_EARTH = 'Earth';
    const ELEMENT_LIGHT = 'Light';
    const ELEMENT_DARK = 'Dark';

    /**
     * ユニットレアリティ
     */
    const RARITY_UR = 'UR';
    const RARITY_SSR = 'SSR';
    const RARITY_SR = 'SR';
    const RARITY_R = 'R';
    const RARITY_UC = 'UC';
    const RARITY_C = 'C';

    /**
     * 全タイプの配列を取得
     * 
     * @return array
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
     * @return array
     */
    public static function getAllElements(): array
    {
        return [
            self::ELEMENT_FIRE,
            self::ELEMENT_WATER,
            self::ELEMENT_WIND,
            self::ELEMENT_EARTH,
            self::ELEMENT_LIGHT,
            self::ELEMENT_DARK,
        ];
    }

    /**
     * 全レアリティの配列を取得
     * 
     * @return array
     */
    public static function getAllRarities(): array
    {
        return [
            self::RARITY_UR,
            self::RARITY_SSR,
            self::RARITY_SR,
            self::RARITY_R,
            self::RARITY_UC,
            self::RARITY_C,
        ];
    }

    /**
     * タイプが有効かチェック
     * 
     * @param string $type
     * @return bool
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::getAllTypes(), true);
    }

    /**
     * 属性が有効かチェック
     * 
     * @param string $element
     * @return bool
     */
    public static function isValidElement(string $element): bool
    {
        return in_array($element, self::getAllElements(), true);
    }

    /**
     * レアリティが有効かチェック
     * 
     * @param string $rarity
     * @return bool
     */
    public static function isValidRarity(string $rarity): bool
    {
        return in_array($rarity, self::getAllRarities(), true);
    }
}
