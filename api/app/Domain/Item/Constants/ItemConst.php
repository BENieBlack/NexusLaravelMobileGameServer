<?php

namespace App\Domain\Item\Constants;

/**
 * アイテム関連の定数定義
 *
 * アイテムのタイプ、効果の定数を管理
 */
class ItemConst
{
    /**
     * アイテムタイプ
     */
    const TYPE_CONSUMABLE = 'Consumable';

    const TYPE_MATERIAL = 'Material';

    const TYPE_EQUIPMENT_ENHANCEMENT = 'EquipmentEnhancement';

    const TYPE_UNIT_ENHANCEMENT = 'UnitEnhancement';

    const TYPE_CURRENCY = 'Currency';

    const TYPE_TICKET = 'Ticket';

    /**
     * アイテム効果
     */
    const EFFECT_HP_RECOVERY = 'HPRecovery';

    const EFFECT_STAMINA_RECOVERY = 'StaminaRecovery';

    const EFFECT_EXP_BOOST = 'ExpBoost';

    const EFFECT_GOLD_BOOST = 'GoldBoost';

    const EFFECT_GACHA_TICKET = 'GachaTicket';

    const EFFECT_UNIT_EXP = 'UnitExp';

    const EFFECT_EQUIPMENT_EXP = 'EquipmentExp';

    const EFFECT_PLAYER_EXP = 'PlayerExp';

    /**
     * ユニット経験値アイテムID
     */
    const UNIT_EXP_ITEM_100 = 'unit_exp_100';       // 100 exp

    const UNIT_EXP_ITEM_1000 = 'unit_exp_1000';     // 1000 exp

    const UNIT_EXP_ITEM_10000 = 'unit_exp_10000';   // 10000 exp

    const UNIT_EXP_ITEM_100000 = 'unit_exp_100000'; // 100000 exp

    /**
     * 全タイプの配列を取得
     */
    public static function getAllTypes(): array
    {
        return [
            self::TYPE_CONSUMABLE,
            self::TYPE_MATERIAL,
            self::TYPE_EQUIPMENT_ENHANCEMENT,
            self::TYPE_UNIT_ENHANCEMENT,
            self::TYPE_CURRENCY,
            self::TYPE_TICKET,
        ];
    }

    /**
     * 全効果の配列を取得
     */
    public static function getAllEffects(): array
    {
        return [
            self::EFFECT_HP_RECOVERY,
            self::EFFECT_STAMINA_RECOVERY,
            self::EFFECT_EXP_BOOST,
            self::EFFECT_GOLD_BOOST,
            self::EFFECT_GACHA_TICKET,
            self::EFFECT_UNIT_EXP,
            self::EFFECT_EQUIPMENT_EXP,
            self::EFFECT_PLAYER_EXP,
        ];
    }

    /**
     * タイプが有効かチェック
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::getAllTypes(), true);
    }

    /**
     * 効果が有効かチェック
     */
    public static function isValidEffect(string $effect): bool
    {
        return in_array($effect, self::getAllEffects(), true);
    }
}
