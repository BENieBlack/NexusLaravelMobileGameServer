<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MstInAppPurchase extends _BaseMst
{
    public $table = 'mst_in_app_purchase';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'type',
        'paid_diamond_amount',
        'vip_point',
        'effect_duration_days',
        'purchase_limit',
        'purchase_limit_reset',
        'app_store_product_id',
        'google_play_product_id',
        'sort_desc',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'paid_diamond_amount' => 'integer',
        'vip_point' => 'integer',
        'effect_duration_days' => 'integer',
        'purchase_limit' => 'integer',
        'sort_desc' => 'integer',
        'is_active' => 'boolean',
    ];

    public $timestamps = true;

    /**
     * AppStore側のプラットフォーム商品
     */
    /**
     * @return BelongsTo<MstBillingPlatformProduct, $this>
     */
    public function appStoreProduct(): BelongsTo
    {
        return $this->belongsTo(MstBillingPlatformProduct::class, 'app_store_product_id');
    }

    /**
     * GooglePlay側のプラットフォーム商品
     */
    /**
     * @return BelongsTo<MstBillingPlatformProduct, $this>
     */
    public function googlePlayProduct(): BelongsTo
    {
        return $this->belongsTo(MstBillingPlatformProduct::class, 'google_play_product_id');
    }

    /**
     * 商品コンテンツ（Pack/Pass用）
     */
    /**
     * @return HasMany<MstInAppPurchaseContent, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(MstInAppPurchaseContent::class, 'mst_in_app_purchase_id');
    }

    /**
     * Pass商品の効果
     */
    /**
     * @return HasMany<MstInAppPurchaseEffect, $this>
     */
    public function effects(): HasMany
    {
        return $this->hasMany(MstInAppPurchaseEffect::class, 'mst_in_app_purchase_id');
    }

    /**
     * IDを取得
     */
    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    /**
     * 課金タイプを取得
     */
    public function getType(): string
    {
        return $this->getAttribute('type');
    }

    /**
     * 有償ダイヤモンド数を取得
     */
    public function getPaidDiamondAmount(): ?int
    {
        return $this->getAttribute('paid_diamond_amount');
    }

    /**
     * VIPポイントを取得
     */
    public function getVipPoint(): int
    {
        return $this->getAttribute('vip_point');
    }

    /**
     * 効果期間（日数）を取得
     */
    public function getEffectDurationDays(): ?int
    {
        return $this->getAttribute('effect_duration_days');
    }

    /**
     * 購入制限を取得
     */
    public function getPurchaseLimit(): ?int
    {
        return $this->getAttribute('purchase_limit');
    }

    /**
     * 購入制限リセット期間を取得
     */
    public function getPurchaseLimitReset(): ?string
    {
        return $this->getAttribute('purchase_limit_reset');
    }

    /**
     * レスポンス用配列に変換
     *
     * データベース層の'id'をAPI層の'mst_in_app_purchase_id'に変換
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();

        if (isset($array['id'])) {
            $array['mst_in_app_purchase_id'] = $array['id'];
            unset($array['id']);
        }

        return $array;
    }
}
