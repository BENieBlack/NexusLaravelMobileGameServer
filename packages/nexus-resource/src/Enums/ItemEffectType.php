<?php

namespace NexusResource\Enums;

/**
 * ItemEffectType
 *
 * アイテムを使ったときに何が起きるかを表す種別（mst_item.effect の値）
 *
 * mst_item は「効果種別（effect）」と「効果量（value）」の組で1つの効果を表す。
 * 使用時のロジックはこの種別で分岐する。
 *
 * mst_item.type はアイテムの分類（UnitEnhancement, consumable など）で、
 * 表示や絞り込みのための情報。効果の判定にはこちらの effect を使う。
 */
enum ItemEffectType: string
{
    /** プレイヤー経験値を加算する */
    case PLAYER_EXP = 'player_exp';

    /** ユニット経験値を加算する（対象のユニットを指定する必要がある） */
    case UNIT_EXP = 'unit_exp';

    /** 装備経験値を加算する（対象の装備を指定する必要がある） */
    case EQUIPMENT_EXP = 'equipment_exp';

    /** スタミナを回復する */
    case STAMINA_RECOVER = 'stamina_recover';

    /**
     * 効果の適用に対象の指定が要るかどうか
     *
     * ユニット・装備の経験値は「どれに使うか」を指定しないと適用できない。
     * それぞれ専用のレベルアップAPIで対象を受け取る。
     */
    public function requiresTarget(): bool
    {
        return match ($this) {
            self::UNIT_EXP, self::EQUIPMENT_EXP => true,
            self::PLAYER_EXP, self::STAMINA_RECOVER => false,
        };
    }

    /**
     * 文字列から変換（未知の値はnull）
     */
    public static function tryFromEffect(?string $effect): ?self
    {
        return $effect === null ? null : self::tryFrom($effect);
    }
}
