<?php

namespace NexusGuild\Constants;

/**
 * GuildApplyStatus
 * 
 * ギルド加入申請のステータス定数
 */
class GuildApplyStatus
{
    /**
     * 申請中
     */
    public const APPLIED = 'applied';

    /**
     * 承認済み
     */
    public const ACCEPTED = 'accepted';

    /**
     * 却下済み
     */
    public const REJECTED = 'rejected';

    /**
     * 全ステータス
     * 
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::APPLIED,
            self::ACCEPTED,
            self::REJECTED,
        ];
    }

    /**
     * 有効なステータスかチェック
     * 
     * @param string $status
     * @return bool
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}
