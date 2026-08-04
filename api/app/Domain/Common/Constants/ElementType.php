<?php

namespace App\Domain\Common\Constants;

/**
 * ゲーム要素の属性（エレメント）定数
 * 
 * ユニット、装備、スキルなど、ゲーム内の様々な要素に共通する属性定義
 * このゲームタイトル固有のドメイン知識
 */
class ElementType
{
    /**
     * 火属性
     */
    const FIRE = 'Fire';

    /**
     * 水属性
     */
    const WATER = 'Water';

    /**
     * 風属性
     */
    const WIND = 'Wind';

    /**
     * 地属性
     */
    const EARTH = 'Earth';

    /**
     * 光属性
     */
    const LIGHT = 'Light';

    /**
     * 闇属性
     */
    const DARK = 'Dark';

    /**
     * 全属性の配列を取得
     * 
     * @return array
     */
    public static function getAll(): array
    {
        return [
            self::FIRE,
            self::WATER,
            self::WIND,
            self::EARTH,
            self::LIGHT,
            self::DARK,
        ];
    }

    /**
     * 属性が有効かチェック
     * 
     * @param string $element
     * @return bool
     */
    public static function isValid(string $element): bool
    {
        return in_array($element, self::getAll(), true);
    }
}
