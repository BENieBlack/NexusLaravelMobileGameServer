<?php

namespace NexusResource\Enums;

/**
 * ResourceType
 *
 * ゲーム内リソースの種類を定義
 */
enum ResourceType: string
{
    // 通貨系
    case DIAMOND = 'diamond';               // ダイヤモンド（無償）
    case PAID_DIAMOND = 'paid_diamond';     // 有償ダイヤモンド
    case GOLD = 'gold';                     // ゴールド
    case COIN = 'coin';                     // コイン
    
    // リソース系
    case FOOD = 'food';                     // 食料
    case WOOD = 'wood';                     // 木材
    case STONE = 'stone';                   // 石材
    case IRON = 'iron';                     // 鉄
    case STAMINA = 'stamina';               // スタミナ
    case EXPERIENCE = 'experience';         // 経験値
    
    // アイテム系
    case ITEM = 'item';                     // 汎用アイテム
    case CONSUMABLE = 'consumable';         // 消耗品
    case MATERIAL = 'material';             // 素材
    case TICKET = 'ticket';                 // チケット
    
    // キャラクター・装備系
    case UNIT = 'unit';                     // ユニット
    case EQUIPMENT = 'equipment';           // 装備
    case WEAPON = 'weapon';                 // 武器
    case ARMOR = 'armor';                   // 防具
    case ACCESSORY = 'accessory';           // アクセサリー
    
    // ポイント系
    case ALLIANCE_POINTS = 'alliance_points'; // アライアンスポイント
    case PVP_POINTS = 'pvp_points';         // PvPポイント
    case EVENT_POINTS = 'event_points';     // イベントポイント
    case ACHIEVEMENT_POINTS = 'achievement_points'; // 達成ポイント
    
    // その他
    case GACHA_TICKET = 'gacha_ticket';     // ガチャチケット
    case VIP_POINTS = 'vip_points';         // VIPポイント
    case CUSTOM = 'custom';                 // カスタムリソース

    /**
     * ラベルを取得
     */
    public function label(): string
    {
        return match($this) {
            self::DIAMOND => 'ダイヤ',
            self::PAID_DIAMOND => '有償ダイヤ',
            self::GOLD => 'ゴールド',
            self::COIN => 'コイン',
            self::FOOD => '食料',
            self::WOOD => '木材',
            self::STONE => '石材',
            self::IRON => '鉄',
            self::STAMINA => 'スタミナ',
            self::EXPERIENCE => '経験値',
            self::ITEM => 'アイテム',
            self::CONSUMABLE => '消耗品',
            self::MATERIAL => '素材',
            self::TICKET => 'チケット',
            self::UNIT => 'ユニット',
            self::EQUIPMENT => '装備',
            self::WEAPON => '武器',
            self::ARMOR => '防具',
            self::ACCESSORY => 'アクセサリー',
            self::ALLIANCE_POINTS => 'アライアンスポイント',
            self::PVP_POINTS => 'PvPポイント',
            self::EVENT_POINTS => 'イベントポイント',
            self::ACHIEVEMENT_POINTS => '達成ポイント',
            self::GACHA_TICKET => 'ガチャチケット',
            self::VIP_POINTS => 'VIPポイント',
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
            self::GOLD => '🪙',
            self::COIN => '🟡',
            self::FOOD => '🍖',
            self::WOOD => '🪵',
            self::STONE => '🪨',
            self::IRON => '⚙️',
            self::STAMINA => '⚡',
            self::EXPERIENCE => '⭐',
            self::ITEM => '📦',
            self::CONSUMABLE => '🧪',
            self::MATERIAL => '🔩',
            self::TICKET => '🎫',
            self::UNIT => '⚔️',
            self::EQUIPMENT => '🛡️',
            self::WEAPON => '⚔️',
            self::ARMOR => '🛡️',
            self::ACCESSORY => '💍',
            self::ALLIANCE_POINTS => '🏰',
            self::PVP_POINTS => '⚔️',
            self::EVENT_POINTS => '🎉',
            self::ACHIEVEMENT_POINTS => '🏆',
            self::GACHA_TICKET => '🎲',
            self::VIP_POINTS => '👑',
            self::CUSTOM => '❓',
        };
    }

    /**
     * 通貨タイプかどうか
     */
    public function isCurrency(): bool
    {
        return in_array($this, [
            self::DIAMOND,
            self::PAID_DIAMOND,
            self::GOLD,
            self::COIN,
        ]);
    }

    /**
     * リソースタイプかどうか
     */
    public function isResource(): bool
    {
        return in_array($this, [
            self::FOOD,
            self::WOOD,
            self::STONE,
            self::IRON,
            self::STAMINA,
            self::EXPERIENCE,
        ]);
    }

    /**
     * アイテムタイプかどうか
     */
    public function isItem(): bool
    {
        return in_array($this, [
            self::ITEM,
            self::CONSUMABLE,
            self::MATERIAL,
            self::TICKET,
        ]);
    }

    /**
     * 装備タイプかどうか
     */
    public function isEquipment(): bool
    {
        return in_array($this, [
            self::EQUIPMENT,
            self::WEAPON,
            self::ARMOR,
            self::ACCESSORY,
        ]);
    }

    /**
     * ポイントタイプかどうか
     */
    public function isPoints(): bool
    {
        return in_array($this, [
            self::ALLIANCE_POINTS,
            self::PVP_POINTS,
            self::EVENT_POINTS,
            self::ACHIEVEMENT_POINTS,
            self::VIP_POINTS,
        ]);
    }

    /**
     * 文字列から変換
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
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
}
