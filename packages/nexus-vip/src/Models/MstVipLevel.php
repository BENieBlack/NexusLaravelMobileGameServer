<?php

namespace NexusVip\Models;

use Nexus\Core\Models\Mst\_BaseMst;

/**
 * MstVipLevel Model
 *
 * VIPレベルマスターデータ
 *
 * @property int $deploy_key
 * @property string $id
 * @property int $level
 * @property int $required_point
 * @property int $max_stamina_bonus
 * @property int $daily_diamond_bonus
 * @property float $shop_discount_rate
 * @property float $gacha_discount_rate
 * @property string|null $display_badge_url
 * @property int $sort_desc
 * @property bool $is_active
 */
class MstVipLevel extends _BaseMst
{
    protected $table = 'mst_vip_level';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var array<int, string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'level',
        'required_point',
        'max_stamina_bonus',
        'daily_diamond_bonus',
        'shop_discount_rate',
        'gacha_discount_rate',
        'display_badge_url',
        'sort_desc',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'deploy_key' => 'integer',
        'level' => 'integer',
        'required_point' => 'integer',
        'max_stamina_bonus' => 'integer',
        'daily_diamond_bonus' => 'integer',
        'shop_discount_rate' => 'decimal:2',
        'gacha_discount_rate' => 'decimal:2',
        'sort_desc' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;

    /**
     * VIPレベルを取得
     */
    public function getLevel(): int
    {
        return $this->getAttribute('level');
    }

    /**
     * 必要VIPポイントを取得
     */
    public function getRequiredPoint(): int
    {
        return $this->getAttribute('required_point');
    }

    /**
     * スタミナ上限ボーナスを取得
     */
    public function getMaxStaminaBonus(): int
    {
        return $this->getAttribute('max_stamina_bonus');
    }

    /**
     * デイリーダイヤモンドボーナスを取得
     */
    public function calcDailyDiamondBonus(): int
    {
        return $this->getAttribute('daily_diamond_bonus');
    }

    /**
     * ショップ割引率を取得
     */
    public function getShopDiscountRate(): float
    {
        return (float) $this->getAttribute('shop_discount_rate');
    }

    /**
     * ガチャ割引率を取得
     */
    public function getGachaDiscountRate(): float
    {
        return (float) $this->getAttribute('gacha_discount_rate');
    }

    /**
     * バッジ画像URLを取得
     */
    public function getDisplayBadgeUrl(): ?string
    {
        return $this->getAttribute('display_badge_url');
    }

    /**
     * 有効フラグを取得
     */
    public function isActive(): bool
    {
        return $this->getAttribute('is_active');
    }

    /**
     * レスポンス用配列に変換
     */
    public function toResponseArray(): array
    {
        return [
            'level' => $this->getLevel(),
            'required_point' => $this->getRequiredPoint(),
            'benefits' => [
                'max_stamina_bonus' => $this->getMaxStaminaBonus(),
                'daily_diamond_bonus' => $this->calcDailyDiamondBonus(),
                'shop_discount_rate' => $this->getShopDiscountRate(),
                'gacha_discount_rate' => $this->getGachaDiscountRate(),
            ],
            'display_badge_url' => $this->getDisplayBadgeUrl(),
        ];
    }
}
