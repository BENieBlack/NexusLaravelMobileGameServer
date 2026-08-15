<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ?int $price_amount_micros
 * @property ?string $price_currency_code
 */
class MstBillingPlatformProduct extends _BaseMst
{
    public $table = 'mst_billing_platform_product';

    protected $fillable = [
        'deploy_key',
        'platform_product_id',
        'billing_platform',
        'product_type',
        'price_amount_micros',
        'price_currency_code',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'deploy_key' => 'integer',
        'price_amount_micros' => 'integer',
        'is_active' => 'boolean',
    ];

    public $timestamps = true;

    /**
     * このプラットフォーム商品を使用しているアプリ内課金商品（AppStore側）
     */
    public function inAppPurchasesAsAppStore(): HasMany
    {
        return $this->hasMany(MstInAppPurchase::class, 'app_store_product_id');
    }

    /**
     * このプラットフォーム商品を使用しているアプリ内課金商品（GooglePlay側）
     */
    public function inAppPurchasesAsGooglePlay(): HasMany
    {
        return $this->hasMany(MstInAppPurchase::class, 'google_play_product_id');
    }

    /**
     * レスポンス用配列に変換
     *
     * データベース層の'id'をAPI層の'mst_billing_platform_product_id'に変換
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();

        if (isset($array['id'])) {
            $array['mst_billing_platform_product_id'] = $array['id'];
            unset($array['id']);
        }

        return $array;
    }
}
