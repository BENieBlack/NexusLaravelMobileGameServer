<?php

namespace App\Models\Mst;

/**
 * MstGachaPrize Model
 * 
 * @property int $deploy_key
 * @property string $id
 * @property string $mst_gacha_id
 * @property int $rarity
 * @property string $content_type
 * @property string $content_id
 * @property int $amount
 * @property int $weight
 * @property bool $is_pickup
 * @property bool $is_active
 */
class MstGachaPrize extends _BaseMst
{
    public $table = 'mst_gacha_prize';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'deploy_key',
        'id',
        'mst_gacha_id',
        'rarity',
        'content_type',
        'content_id',
        'content_option',
        'content_quantity',
        'amount',
        'weight',
        'is_pickup',
        'is_active',
    ];

    protected $casts = [
        'deploy_key' => 'integer',
        'rarity' => 'integer',
        'content_option' => 'array',
        'content_quantity' => 'integer',
        'amount' => 'integer',
        'weight' => 'integer',
        'is_pickup' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;

    /**
     * コンテンツタイプを取得
     *
     * @return string
     */
    public function getContentType(): string
    {
        return $this->getAttribute('content_type');
    }

    /**
     * コンテンツIDを取得
     *
     * @return string
     */
    public function getContentId(): string
    {
        return $this->getAttribute('content_id');
    }

    /**
     * コンテンツオプションを取得
     *
     * @return array|null
     */
    public function getContentOption(): ?array
    {
        return $this->getAttribute('content_option');
    }

    /**
     * コンテンツ数量を取得（1配布あたり）
     *
     * @return int
     */
    public function getContentQuantity(): int
    {
        return $this->getAttribute('content_quantity');
    }

    /**
     * 配布回数を取得
     *
     * @return int
     */
    public function getAmount(): int
    {
        return $this->getAttribute('amount');
    }

    /**
     * 実際の配布総量を取得（content_quantity × amount）
     *
     * @return int
     */
    public function getTotalQuantity(): int
    {
        return $this->getContentQuantity() * $this->getAmount();
    }
}
