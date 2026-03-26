<?php

namespace App\Domain\Delivery\DTOs;

use App\Domain\Delivery\Constants\DeliveryConst;
use Carbon\CarbonImmutable;

/**
 * DeliveryContent
 *
 * 配送する報酬コンテンツのデータ構造
 * Item, Unit, Equipment, Diamond, Wallet通貨などを統一的に表現
 */
readonly class DeliveryContent
{

    /**
     * @param string $type リソースタイプ (item, unit, equipment, diamond, wallet)
     * @param string $id マスターID (mst_item_id, mst_unit_id等)
     * @param int $amount 数量
     * @param CarbonImmutable|null $expireAt 有効期限（Wallet通貨用、NULLは無期限）
     * @param array|null $metadata 追加情報（Unit: grade/level, Equipment: 初期値等）
     */
    public function __construct(
        public string $type,
        public string $id,
        public int $amount,
        public ?CarbonImmutable $expireAt = null,
        public ?array $metadata = null,
    ) {
    }

    /**
     * 配列からDeliveryContentを生成
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            id: $data['id'],
            amount: $data['amount'],
            expireAt: isset($data['expire_at']) ? CarbonImmutable::parse($data['expire_at']) : null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * 配列に変換
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'amount' => $this->amount,
            'expire_at' => $this->expireAt?->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Itemタイプの配送データを作成
     *
     * @param string $mstItemId
     * @param int $amount
     * @return self
     */
    public static function item(string $mstItemId, int $amount): self
    {
        return new self(
            type: DeliveryConst::CONTENT_TYPE_ITEM,
            id: $mstItemId,
            amount: $amount,
        );
    }

    /**
     * Unitタイプの配送データを作成
     *
     * @param string $mstUnitId
     * @param int $amount
     * @param int|null $grade
     * @param int|null $level
     * @return self
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
            type: DeliveryConst::CONTENT_TYPE_UNIT,
            id: $mstUnitId,
            amount: $amount,
            metadata: empty($metadata) ? null : $metadata,
        );
    }

    /**
     * Equipmentタイプの配送データを作成
     *
     * @param string $mstEquipmentId
     * @param int $amount
     * @return self
     */
    public static function equipment(string $mstEquipmentId, int $amount): self
    {
        return new self(
            type: DeliveryConst::CONTENT_TYPE_EQUIPMENT,
            id: $mstEquipmentId,
            amount: $amount,
        );
    }

    /**
     * Diamondタイプの配送データを作成
     *
     * @param int $amount
     * @param bool $isPaid 有償ダイヤモンドか（falseの場合は無償）
     * @return self
     */
    public static function diamond(int $amount, bool $isPaid = false): self
    {
        return new self(
            type: DeliveryConst::CONTENT_TYPE_DIAMOND,
            id: 'diamond', // Diamondは固定ID
            amount: $amount,
            metadata: ['is_paid' => $isPaid],
        );
    }

    /**
     * Walletタイプの配送データを作成
     *
     * @param string $mstItemId 通貨アイテムID (gold, event_coin等)
     * @param int $amount
     * @param CarbonImmutable|null $expireAt 有効期限
     * @return self
     */
    public static function wallet(string $mstItemId, int $amount, ?CarbonImmutable $expireAt = null): self
    {
        return new self(
            type: DeliveryConst::CONTENT_TYPE_WALLET,
            id: $mstItemId,
            amount: $amount,
            expireAt: $expireAt,
        );
    }
}
