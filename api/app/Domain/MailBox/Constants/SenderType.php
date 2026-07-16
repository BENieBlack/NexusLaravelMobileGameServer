<?php

namespace App\Domain\MailBox\Constants;

/**
 * メール送信者タイプ定数
 */
enum SenderType: string
{
    case SYSTEM = 'System';           // システム
    case PLAYER = 'Player';           // プレイヤー
    case ALLIANCE = 'Alliance';       // アライアンス
    case NPC = 'NPC';                 // NPC

    /**
     * ラベルを取得
     */
    public function label(): string
    {
        return match($this) {
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
