<?php

namespace App\Domain\Mailbox\Constants;

/**
 * ContentType
 *
 * メールボックスコンテンツのタイプ定数
 */
class ContentType
{
    /**
     * コンテンツタイプ定数
     */
    public const DIAMOND = 'Diamond';
    public const ITEM = 'Item';
    public const UNIT = 'Unit';
    public const EQUIPMENT = 'Equipment';

    /**
     * 全てのタイプを取得
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::DIAMOND,
            self::ITEM,
            self::UNIT,
            self::EQUIPMENT,
        ];
    }

    /**
     * 有効なタイプかどうかを判定
     *
     * @param string $type
     * @return bool
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
