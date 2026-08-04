<?php

namespace NexusVip\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * VIPレベルアップ報酬マスターモデル
 */
class MstVipLevelReward extends Model
{
    protected $connection = 'mst';
    protected $table = 'mst_vip_level_reward';
    
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

    protected $casts = [
        'deploy_key' => 'integer',
        'vip_level' => 'integer',
        'content_option' => 'array',
        'content_quantity' => 'integer',
        'amount' => 'integer',
        'is_paid' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * VIPレベルを取得
     */
    public function getVipLevel(): int
    {
        return $this->vip_level;
    }

    /**
     * コンテンツタイプを取得
     */
    public function getContentType(): string
    {
        return $this->content_type;
    }

    /**
     * コンテンツIDを取得
     */
    public function getContentId(): string
    {
        return $this->content_id;
    }

    /**
     * コンテンツオプションを取得
     */
    public function getContentOption(): ?array
    {
        return $this->content_option;
    }

    /**
     * コンテンツ数量を取得（1配布あたり）
     */
    public function getContentQuantity(): int
    {
        return $this->content_quantity;
    }

    /**
     * 配布回数を取得
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * 実際の配布総量を取得（content_quantity × amount）
     */
    public function getTotalQuantity(): int
    {
        return $this->getContentQuantity() * $this->getAmount();
    }

    /**
     * 有償フラグを取得
     */
    public function getIsPaid(): bool
    {
        return $this->is_paid;
    }

    /**
     * 有効フラグを取得
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }
}
