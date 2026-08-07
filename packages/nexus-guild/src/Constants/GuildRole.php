<?php

namespace NexusGuild\Constants;

/**
 * GuildRole
 * 
 * ギルドメンバーの役職定数
 */
class GuildRole
{
    /**
     * マスター（ギルドリーダー）
     */
    public const MASTER = 'master';

    /**
     * サブマスター（副リーダー）
     */
    public const SUB_MASTER = 'sub_master';

    /**
     * メンバー（一般メンバー）
     */
    public const MEMBER = 'member';

    /**
     * 全役職
     * 
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::MASTER,
            self::SUB_MASTER,
            self::MEMBER,
        ];
    }

    /**
     * 有効な役職かチェック
     * 
     * @param string $role
     * @return bool
     */
    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }
}
