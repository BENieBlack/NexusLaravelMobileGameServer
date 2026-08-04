<?php

namespace App\Models\Mst;

class MstVipLevelReward extends _BaseMst
{
    public $table = 'mst_vip_level_reward';
    
    public $incrementing = false;
    protected $primaryKey = ['vip_level', 'content_type', 'content_id'];

    protected $fillable = [
        'deploy_key',
        'vip_level',
        'content_type',
        'content_id',
        'content_option',
        'content_quantity',
        'amount',
        'is_paid',
        'sort_order',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'deploy_key' => 'integer',
        'vip_level' => 'integer',
        'content_option' => 'array',
        'content_quantity' => 'integer',
        'amount' => 'integer',
        'is_paid' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;

    /**
     * VIPレベルを取得
     *
     * @return int
     */
    public function getVipLevel(): int
    {
        return $this->getAttribute('vip_level');
    }

    /**
     * コンテンツタイプを取得
     *
     * @return string item, unit, equipment, diamond, wallet, stamina
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

    /**
     * 有償フラグを取得
     *
     * @return bool
     */
    public function getIsPaid(): bool
    {
        return $this->getAttribute('is_paid');
    }

    /**
     * 表示順序を取得
     *
     * @return int
     */
    public function getSortOrder(): int
    {
        return $this->getAttribute('sort_order');
    }

    /**
     * 有効フラグを取得
     *
     * @return bool
     */
    public function getIsActive(): bool
    {
        return $this->getAttribute('is_active');
    }

    /**
     * レスポンス用配列に変換
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        return [
            'vip_level' => $this->getVipLevel(),
            'content_type' => $this->getContentType(),
            'content_id' => $this->getContentId(),
            'content_option' => $this->getContentOption(),
            'content_quantity' => $this->getContentQuantity(),
            'amount' => $this->getAmount(),
            'total_quantity' => $this->getTotalQuantity(),
            'is_paid' => $this->getIsPaid(),
            'sort_order' => $this->getSortOrder(),
        ];
    }
}
