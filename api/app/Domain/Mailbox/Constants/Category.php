<?php

namespace App\Domain\Mailbox\Constants;

/**
 * メールボックスのカテゴリ定数
 */
enum Category: string
{
    case SYSTEM = 'system';           // システムメッセージ
    case BATTLE = 'battle';           // 戦闘レポート
    case ALLIANCE = 'alliance';       // アライアンス関連
    case FRIEND = 'friend';           // フレンド関連
    case TRADE = 'trade';             // 取引関連
    case REWARD = 'reward';           // 報酬
    case PERSONAL = 'personal';       // 個人メッセージ

    /**
     * ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            self::SYSTEM => 'システム',
            self::BATTLE => '戦闘レポート',
            self::ALLIANCE => 'アライアンス',
            self::FRIEND => 'フレンド',
            self::TRADE => '取引',
            self::REWARD => '報酬',
            self::PERSONAL => '個人',
        };
    }

    /**
     * アイコンを取得
     */
    public function icon(): string
    {
        return match ($this) {
            self::SYSTEM => '⚙️',
            self::BATTLE => '⚔️',
            self::ALLIANCE => '🏰',
            self::FRIEND => '👥',
            self::TRADE => '💱',
            self::REWARD => '🎁',
            self::PERSONAL => '💌',
        };
    }

    /**
     * 全カテゴリを取得
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * 文字列から変換
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
