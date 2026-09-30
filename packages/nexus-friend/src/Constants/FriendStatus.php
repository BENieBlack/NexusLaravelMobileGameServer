<?php

namespace NexusFriend\Constants;

/**
 * FriendStatus
 *
 * フレンド申請のステータス定数
 */
class FriendStatus
{
    /**
     * 申請中
     */
    public const APPLIED = 'applied';

    /**
     * 承認済み（フレンド関係成立）
     */
    public const ACCEPTED = 'accepted';

    /**
     * 却下済み
     */
    public const REJECTED = 'rejected';

    /**
     * 削除済み（論理削除）
     */
    public const DELETED = 'deleted';

    /**
     * すべてのステータス値を取得
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::APPLIED,
            self::ACCEPTED,
            self::REJECTED,
            self::DELETED,
        ];
    }

    /**
     * 有効なステータスかチェック
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
