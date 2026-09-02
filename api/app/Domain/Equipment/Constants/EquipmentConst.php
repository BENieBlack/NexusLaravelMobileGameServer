<?php

namespace App\Domain\Equipment\Constants;

/**
 * 装備関連の定数定義
 *
 * 装備のタイプ定数を管理
 * 属性・レアリティは共通定数（App\Domain\Common\Constants）を使用
 */
class EquipmentConst
{
    /**
     * 装備タイプ
     */
    const TYPE_ATTACK = 'attack';

    const TYPE_DEFENSE = 'defense';

    const TYPE_SUPPORT = 'support';

    /**
     * 全タイプの配列を取得
     *
     * @return array<int, string>
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
