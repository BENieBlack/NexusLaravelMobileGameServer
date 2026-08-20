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
     * 装備強化に使う経験値アイテムのID（mst_item.id）
     *
     * マスタ側で持つべき情報だが、現状 mst_item に「強化素材かどうか」の
     * 区分が無いため、ここで固定している。
     * 環境ごとに変わる値ではないため、.envや設定ファイルには置かない。
     */
    const EXP_ITEM_ID = 'equipment_exp_potion';

    /**
     * 装備タイプ
     */
    const TYPE_ATTACK = 'Attack';

    const TYPE_DEFENSE = 'Defense';

    const TYPE_SUPPORT = 'Support';

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
