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
    const FIRE = 'fire';

    /**
     * 水属性
     */
    const WATER = 'water';

    /**
     * 風属性
     */
    const WIND = 'wind';

    /**
     * 地属性
     */
    const EARTH = 'earth';

    /**
     * 光属性
     */
    const LIGHT = 'light';

    /**
     * 闇属性
     */
    const DARK = 'dark';

    /**
     * 全属性の配列を取得
     *
     * @return array<int, string>
     */
    public static function all(): array
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
     */
    public static function isValid(string $element): bool
    {
        return in_array($element, self::all(), true);
    }
}
