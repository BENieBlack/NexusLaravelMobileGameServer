<?php

namespace NexusChat\Constants;

/**
 * ChatRoomRole
 *
 * グループチャットのメンバーロール定数
 *
 * FRIEND / GUILD チャットではロールは使用しない。
 * GROUP チャットのみで有効。
 */
enum ChatRoomRole: string
{
    // ルーム作成者。ルームの解散・全権限を持つ
    case OWNER = 'owner';

    // 管理者。メンバー招待・キックが可能
    case ADMIN = 'admin';

    // 一般メンバー。メッセージ送受信のみ
    case MEMBER = 'member';

    /**
     * 招待権限があるか
     */
    public function canInvite(): bool
    {
        return match ($this) {
            self::OWNER, self::ADMIN => true,
            self::MEMBER => false,
        };
    }

    /**
     * メンバーをキックする権限があるか
     */
    public function canKick(): bool
    {
        return match ($this) {
            self::OWNER, self::ADMIN => true,
            self::MEMBER => false,
        };
    }

    /**
     * ロールを昇格・降格する権限があるか（OWNERのみ）
     */
    public function canManageRoles(): bool
    {
        return $this === self::OWNER;
    }
}
