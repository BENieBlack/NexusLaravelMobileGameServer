<?php

namespace NexusResource\DataTransferObjects;

use NexusResource\Enums\ResourceType;
use Ramsey\Uuid\Uuid;

/**
 * Resource
 *
 * ゲーム内リソースの基本データ構造
 * Diamond、Unit、Equipment、Coin、Itemなど全てのリソースを統一的に表現
 */
class Resource
{
    /** @var string 一意のID */
    private string $uniqueId;

    /**
     * @param  ResourceType  $type  リソースタイプ
     * @param  string  $id  マスターID (mst_item_id, mst_unit_id等、通貨系は固定値)
     * @param  int  $amount  数量
     * @param  string|null  $expireAt  有効期限（Y-m-d H:i:s形式、NULLは無期限）
     * @param  array<string, mixed>|null  $metadata  追加情報（Unit: grade/level, Equipment: 初期値等）
     */
    public function __construct(
        private ResourceType $type,
        private string $id,
        private int $amount,
        private ?string $expireAt = null,
        private ?array $metadata = null,
    ) {
        $this->uniqueId = $this->generateUniqueId();
    }

    /**
     * 一意のIDを生成
     */
    private function generateUniqueId(): string
    {
        return (string) Uuid::uuid4();
    }

    /**
     * 一意のIDを取得
     */
    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    /**
     * リソースタイプを取得
     */
    public function getType(): ResourceType
    {
        return $this->type;
    }

    /**
     * タイプの文字列を取得
     */
    public function getTypeValue(): string
    {
        return $this->type->value;
    }

    /**
     * マスターIDを取得
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 数量を取得
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * 数量を設定
     */
    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * 有効期限を取得
     *
     * @return string|null Y-m-d H:i:s形式の日時文字列
     */
    public function getExpireAt(): ?string
    {
        return $this->expireAt;
    }

    /**
     * メタデータを取得
     *
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * メタデータを設定
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * 有効なリソースかどうか（数量が1以上）
     */
    public function isValid(): bool
    {
        return $this->amount > 0;
    }

    /**
     * 配列からResourceを生成
     *
     * @param  array{type: ResourceType|string, id: string, amount: int, expire_at?: string|null, metadata?: array<string, mixed>|null}  $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] instanceof ResourceType
            ? $data['type']
            : ResourceType::from($data['type']);

        return new self(
            type: $type,
            id: $data['id'],
            amount: $data['amount'],
            expireAt: $data['expire_at'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * 文字列のタイプからResourceを生成
     *
     * @param  string  $typeString  リソースタイプ文字列 ('item', 'unit', 'diamond', etc.)
     * @param  string  $id  マスターID
     * @param  int  $amount  数量
     * @param  string|null  $expireAt  有効期限
     * @param  array<string, mixed>|null  $metadata  追加情報
     * @return self
     *
     * @throws \ValueError タイプ文字列が不正な場合
     */
    public static function fromTypeString(
        string $typeString,
        string $id,
        int $amount,
        ?string $expireAt = null,
        ?array $metadata = null
    ): self {
        $type = ResourceType::fromString($typeString);

        if ($type === null) {
            throw new \ValueError("Invalid resource type: {$typeString}");
        }

        return new self(
            type: $type,
            id: $id,
            amount: $amount,
            expireAt: $expireAt,
            metadata: $metadata,
        );
    }

    /**
     * 配列に変換
     *
     * @return array{unique_id: string, type: string, id: string, amount: int, expire_at: string|null, metadata: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'unique_id' => $this->uniqueId,
            'type' => $this->type->value,
            'id' => $this->id,
            'amount' => $this->amount,
            'expire_at' => $this->expireAt,
            'metadata' => $this->metadata,
        ];
    }

    // ===== Factory Methods =====

    /**
     * Diamond（無償）リソースを作成
     */
    public static function diamond(int $amount): self
    {
        return new self(
            type: ResourceType::DIAMOND,
            id: 'diamond',
            amount: $amount,
        );
    }

    /**
     * PaidDiamond（有償）リソースを作成
     */
    public static function paidDiamond(int $amount): self
    {
        return new self(
            type: ResourceType::PAID_DIAMOND,
            id: 'paid_diamond',
            amount: $amount,
        );
    }

    /**
     * Goldリソースを作成
     */
    public static function gold(int $amount, ?string $expireAt = null): self
    {
        return new self(
            type: ResourceType::GOLD,
            id: 'gold',
            amount: $amount,
            expireAt: $expireAt,
        );
    }

    /**
     * Coinリソースを作成
     */
    public static function coin(int $amount, ?string $expireAt = null): self
    {
        return new self(
            type: ResourceType::COIN,
            id: 'coin',
            amount: $amount,
            expireAt: $expireAt,
        );
    }

    /**
     * Foodリソースを作成
     */
    public static function food(int $amount): self
    {
        return new self(
            type: ResourceType::FOOD,
            id: 'food',
            amount: $amount,
        );
    }

    /**
     * Woodリソースを作成
     */
    public static function wood(int $amount): self
    {
        return new self(
            type: ResourceType::WOOD,
            id: 'wood',
            amount: $amount,
        );
    }

    /**
     * Stoneリソースを作成
     */
    public static function stone(int $amount): self
    {
        return new self(
            type: ResourceType::STONE,
            id: 'stone',
            amount: $amount,
        );
    }

    /**
     * Ironリソースを作成
     */
    public static function iron(int $amount): self
    {
        return new self(
            type: ResourceType::IRON,
            id: 'iron',
            amount: $amount,
        );
    }

    /**
     * Staminaリソースを作成
     */
    public static function stamina(int $amount): self
    {
        return new self(
            type: ResourceType::STAMINA,
            id: 'stamina',
            amount: $amount,
        );
    }

    /**
     * Experienceリソースを作成
     */
    public static function experience(int $amount): self
    {
        return new self(
            type: ResourceType::EXPERIENCE,
            id: 'experience',
            amount: $amount,
        );
    }

    /**
     * Itemリソースを作成
     */
    public static function item(string $mstItemId, int $amount): self
    {
        return new self(
            type: ResourceType::ITEM,
            id: $mstItemId,
            amount: $amount,
        );
    }

    /**
     * Consumableリソースを作成
     */
    public static function consumable(string $mstItemId, int $amount): self
    {
        return new self(
            type: ResourceType::CONSUMABLE,
            id: $mstItemId,
            amount: $amount,
        );
    }

    /**
     * Materialリソースを作成
     */
    public static function material(string $mstItemId, int $amount): self
    {
        return new self(
            type: ResourceType::MATERIAL,
            id: $mstItemId,
            amount: $amount,
        );
    }

    /**
     * Ticketリソースを作成
     */
    public static function ticket(string $mstItemId, int $amount): self
    {
        return new self(
            type: ResourceType::TICKET,
            id: $mstItemId,
            amount: $amount,
        );
    }

    /**
     * Unitリソースを作成
     */
    public static function unit(string $mstUnitId, int $amount, ?int $grade = null, ?int $level = null): self
    {
        $metadata = [];
        if ($grade !== null) {
            $metadata['grade'] = $grade;
        }
        if ($level !== null) {
            $metadata['level'] = $level;
        }

        return new self(
            type: ResourceType::UNIT,
            id: $mstUnitId,
            amount: $amount,
            metadata: empty($metadata) ? null : $metadata,
        );
    }

    /**
     * Equipmentリソースを作成
     */
    public static function equipment(string $mstEquipmentId, int $amount): self
    {
        return new self(
            type: ResourceType::EQUIPMENT,
            id: $mstEquipmentId,
            amount: $amount,
        );
    }

    /**
     * Weaponリソースを作成
     */
    public static function weapon(string $mstWeaponId, int $amount): self
    {
        return new self(
            type: ResourceType::WEAPON,
            id: $mstWeaponId,
            amount: $amount,
        );
    }

    /**
     * Armorリソースを作成
     */
    public static function armor(string $mstArmorId, int $amount): self
    {
        return new self(
            type: ResourceType::ARMOR,
            id: $mstArmorId,
            amount: $amount,
        );
    }

    /**
     * Accessoryリソースを作成
     */
    public static function accessory(string $mstAccessoryId, int $amount): self
    {
        return new self(
            type: ResourceType::ACCESSORY,
            id: $mstAccessoryId,
            amount: $amount,
        );
    }

    /**
     * AlliancePointsリソースを作成
     */
    public static function alliancePoints(int $amount): self
    {
        return new self(
            type: ResourceType::ALLIANCE_POINTS,
            id: 'alliance_points',
            amount: $amount,
        );
    }

    /**
     * PvPPointsリソースを作成
     */
    public static function pvpPoints(int $amount): self
    {
        return new self(
            type: ResourceType::PVP_POINTS,
            id: 'pvp_points',
            amount: $amount,
        );
    }

    /**
     * EventPointsリソースを作成
     */
    public static function eventPoints(int $amount): self
    {
        return new self(
            type: ResourceType::EVENT_POINTS,
            id: 'event_points',
            amount: $amount,
        );
    }

    /**
     * AchievementPointsリソースを作成
     */
    public static function achievementPoints(int $amount): self
    {
        return new self(
            type: ResourceType::ACHIEVEMENT_POINTS,
            id: 'achievement_points',
            amount: $amount,
        );
    }

    /**
     * GachaTicketリソースを作成
     */
    public static function gachaTicket(string $mstTicketId, int $amount): self
    {
        return new self(
            type: ResourceType::GACHA_TICKET,
            id: $mstTicketId,
            amount: $amount,
        );
    }

    /**
     * VIPPointsリソースを作成
     */
    public static function vipPoints(int $amount): self
    {
        return new self(
            type: ResourceType::VIP_POINTS,
            id: 'vip_points',
            amount: $amount,
        );
    }

    /**
     * Customリソースを作成
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public static function custom(string $customId, int $amount, ?array $metadata = null): self
    {
        return new self(
            type: ResourceType::CUSTOM,
            id: $customId,
            amount: $amount,
            metadata: $metadata,
        );
    }
}
