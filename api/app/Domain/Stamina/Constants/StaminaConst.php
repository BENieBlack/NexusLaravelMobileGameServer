<?php

namespace App\Domain\Stamina\Constants;

/**
 * スタミナ定数クラス
 *
 * スタミナタイプの定義と関連定数を管理
 */
class StaminaConst
{
    /**
     * スタミナタイプ: 通常スタミナ（クエスト用）
     */
    public const TYPE_NORMAL = 'normal';

    /**
     * スタミナタイプ: レイドスタミナ
     */
    public const TYPE_RAID = 'raid';

    /**
     * スタミナタイプ: PVPスタミナ
     */
    public const TYPE_PVP = 'pvp';

    /**
     * スタミナタイプ: イベント専用スタミナ
     */
    public const TYPE_EVENT = 'event';

    /**
     * 全てのスタミナタイプを取得
     *
     * @return array<string>
     */
    public static function getAllTypes(): array
    {
        return [
            self::TYPE_NORMAL,
            self::TYPE_RAID,
            self::TYPE_PVP,
            self::TYPE_EVENT,
        ];
    }

    /**
     * スタミナタイプの表示名を取得
     */
    public static function getTypeName(string $type): string
    {
        return match ($type) {
            self::TYPE_NORMAL => '通常スタミナ',
            self::TYPE_RAID => 'レイドスタミナ',
            self::TYPE_PVP => 'PVPスタミナ',
            self::TYPE_EVENT => 'イベントスタミナ',
            default => '未定義',
        };
    }

    /**
     * 有効なスタミナタイプかチェック
     */
    public static function isValidType(string $type): bool
    {
        return in_array($type, self::getAllTypes(), true);
    }
}
