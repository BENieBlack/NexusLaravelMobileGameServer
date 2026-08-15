<?php

namespace App\Domain\Unit\Constants;

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
     * 全タイプの配列を取得
     */
    public static function allTypes(): array
    {
        return [
            self::TYPE_ATTACK,
            self::TYPE_DEFENSE,
            self::TYPE_SUPPORT,
        ];
    }

    /**
     * タイプが有効かチェック
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::allTypes(), true);
    }
}
