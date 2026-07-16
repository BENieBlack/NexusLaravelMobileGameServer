<?php

namespace App\Domain\Mailbox\Constants;

/**
 * メールボックスコンテンツのタイプ定数
 */
enum ContentType: string
{
    case DIAMOND = 'Diamond';               // ダイヤ
    case PAID_DIAMOND = 'PaidDiamond';      // 有償ダイヤ
    case ITEM = 'Item';                     // アイテム
    case UNIT = 'Unit';                     // ユニット
    case EQUIPMENT = 'Equipment';           // 装備
    case GOLD = 'Gold';                     // ゴールド
    case FOOD = 'Food';                     // 食料
    case WOOD = 'Wood';                     // 木材
    case STONE = 'Stone';                   // 石材
    case STAMINA = 'Stamina';               // スタミナ
    case EXPERIENCE = 'Experience';         // 経験値
    case ALLIANCE_POINTS = 'AlliancePoints'; // アライアンスポイント
    case CUSTOM = 'Custom';                 // カスタムリソース

    /**
     * ラベルを取得
     */
    public function label(): string
    {
        return match($this) {
            self::DIAMOND => 'ダイヤ',
            self::PAID_DIAMOND => '有償ダイヤ',
            self::ITEM => 'アイテム',
            self::UNIT => 'ユニット',
            self::EQUIPMENT => '装備',
            self::GOLD => 'ゴールド',
            self::FOOD => '食料',
            self::WOOD => '木材',
            self::STONE => '石材',
            self::STAMINA => 'スタミナ',
            self::EXPERIENCE => '経験値',
            self::ALLIANCE_POINTS => 'アライアンスポイント',
            self::CUSTOM => 'カスタム',
        };
    }

    /**
     * アイコンを取得
     */
    public function icon(): string
    {
        return match($this) {
            self::DIAMOND => '💎',
            self::PAID_DIAMOND => '💠',
            self::ITEM => '📦',
            self::UNIT => '⚔️',
            self::EQUIPMENT => '🛡️',
            self::GOLD => '🪙',
            self::FOOD => '🍖',
            self::WOOD => '🪵',
            self::STONE => '🪨',
            self::STAMINA => '⚡',
            self::EXPERIENCE => '⭐',
            self::ALLIANCE_POINTS => '🏰',
            self::CUSTOM => '❓',
        };
    }

    /**
     * 全てのタイプを取得
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * 有効なタイプかどうかを判定
     *
     * @param string $type
     * @return bool
     */
    public static function isValid(string $type): bool
    {
        return self::tryFrom($type) !== null;
    }

    /**
     * 文字列から変換
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
