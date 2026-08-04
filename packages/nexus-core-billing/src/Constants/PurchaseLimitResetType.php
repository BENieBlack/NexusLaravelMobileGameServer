<?php

namespace NexusBilling\Constants;

/**
 * 購入制限リセット種別の定数定義
 * 
 * アプリ内課金商品の購入回数制限をいつリセットするかを定義
 */
class PurchaseLimitResetType
{
    /**
     * リセットしない（永久制限）
     */
    const NONE = 'None';

    /**
     * 日次リセット（毎日0時にリセット）
     */
    const DAILY = 'Daily';

    /**
     * 週次リセット（毎週月曜0時にリセット）
     */
    const WEEKLY = 'Weekly';

    /**
     * 月次リセット（毎月1日0時にリセット）
     */
    const MONTHLY = 'Monthly';

    /**
     * 全リセットタイプの配列を取得
     * 
     * @return array
     */
    public static function getAll(): array
    {
        return [
            self::NONE,
            self::DAILY,
            self::WEEKLY,
            self::MONTHLY,
        ];
    }

    /**
     * リセットタイプが有効かチェック
     * 
     * @param string $resetType
     * @return bool
     */
    public static function isValid(string $resetType): bool
    {
        return in_array($resetType, self::getAll(), true);
    }
}
