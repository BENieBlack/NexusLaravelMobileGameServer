<?php

namespace App\Domain\Mailbox\Constants;

/**
 * メール送信者タイプ定数
 */
enum SenderType: string
{
    case SYSTEM = 'system';           // システム
    case PLAYER = 'player';           // プレイヤー
    case ALLIANCE = 'alliance';       // アライアンス
    case NPC = 'npc';                 // NPC

    /**
     * ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            self::SYSTEM => 'システム運営',
            self::PLAYER => 'プレイヤー',
            self::ALLIANCE => 'アライアンス',
            self::NPC => 'NPC',
        };
    }

    /**
     * 文字列から変換
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
