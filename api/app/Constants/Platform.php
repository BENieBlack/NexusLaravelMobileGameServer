<?php

namespace App\Constants;

/**
 * クライアントの配信プラットフォーム
 *
 * X-Platform ヘッダーの許可値。trx_diamond.platform と課金の BuyRequest の
 * platform（Apple / Google）と同じ表記に揃える。
 * 大文字小文字を区別する（apple や iOS は不正）。区別しないと DB に入る値が
 * Apple と apple に割れるため。
 */
final class Platform
{
    public const APPLE = 'Apple';

    public const GOOGLE = 'Google';

    /**
     * 許可する値の一覧
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::APPLE, self::GOOGLE];
    }

    /**
     * 許可する値か（完全一致。前後の空白も許さない）
     */
    public static function isValid(?string $value): bool
    {
        return $value !== null && in_array($value, self::values(), true);
    }
}
