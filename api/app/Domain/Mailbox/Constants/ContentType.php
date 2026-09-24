<?php

namespace App\Domain\Mailbox\Constants;

use NexusResource\Enums\ResourceType;

/**
 * メールボックスコンテンツのタイプ定数
 */
enum ContentType: string
{
    case DIAMOND = 'diamond';               // ダイヤ
    case PAID_DIAMOND = 'paid_diamond';      // 有償ダイヤ
    case ITEM = 'item';                     // アイテム
    case UNIT = 'unit';                     // ユニット
    case EQUIPMENT = 'equipment';           // 装備
    case GOLD = 'gold';                     // ゴールド
    case FOOD = 'food';                     // 食料
    case WOOD = 'wood';                     // 木材
    case STONE = 'stone';                   // 石材
    case STAMINA = 'stamina';               // スタミナ
    case EXPERIENCE = 'experience';         // 経験値
    case ALLIANCE_POINTS = 'alliance_points'; // アライアンスポイント
    case CUSTOM = 'custom';                 // カスタムリソース

    /**
     * 配送側のリソース種別へ変換する
     *
     * メールの種別はパスカルケース、配送側はスネークケースで、
     * PaidDiamond / AlliancePoints のような複数語は小文字化だけでは合わない。
     * 対応を1箇所に書いて、増やしたときに match が落ちるようにしておく。
     */
    public function toResourceType(): ResourceType
    {
        return match ($this) {
            self::DIAMOND => ResourceType::DIAMOND,
            self::PAID_DIAMOND => ResourceType::PAID_DIAMOND,
            self::ITEM => ResourceType::ITEM,
            self::UNIT => ResourceType::UNIT,
            self::EQUIPMENT => ResourceType::EQUIPMENT,
            self::GOLD => ResourceType::GOLD,
            self::FOOD => ResourceType::FOOD,
            self::WOOD => ResourceType::WOOD,
            self::STONE => ResourceType::STONE,
            self::STAMINA => ResourceType::STAMINA,
            self::EXPERIENCE => ResourceType::EXPERIENCE,
            self::ALLIANCE_POINTS => ResourceType::ALLIANCE_POINTS,
            self::CUSTOM => ResourceType::CUSTOM,
        };
    }

    /**
     * ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
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
        return match ($this) {
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
