<?php

namespace NexusMailbox\Constants;

/**
 * メールボックスのカテゴリ定数
 */
enum Category: string
{
    case SYSTEM = 'System';           // システムメッセージ
    case BATTLE = 'Battle';           // 戦闘レポート
    case ALLIANCE = 'Alliance';       // アライアンス関連
    case FRIEND = 'Friend';           // フレンド関連
    case TRADE = 'Trade';             // 取引関連
    case REWARD = 'Reward';           // 報酬
    case PERSONAL = 'Personal';       // 個人メッセージ

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
     * 全カテゴリを取得
     *
     * @return array<string>
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
