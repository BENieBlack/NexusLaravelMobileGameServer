<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstBillingPlatformProduct;
use Nexus\Core\Support\CustomCollection;

/**
 * MstBillingPlatformProductRepository
 *
 * プラットフォーム課金商品マスターのRepository
 *
 * @extends _BaseMstRepository<MstBillingPlatformProduct>
 */
class MstBillingPlatformProductRepository extends _BaseMstRepository
{
    protected string $modelClass = MstBillingPlatformProduct::class;

    /**
     * プラットフォームと商品IDで課金商品を取得
     *
     * @param  string  $billingPlatform  決済プラットフォーム
     * @param  string  $platformProductId  プラットフォーム商品ID
     */
    public function selectByBillingPlatformAndPlatformProductId(
        string $billingPlatform,
        string $platformProductId
    ): ?MstBillingPlatformProduct {
        return $this->queryOrMemory()
            ->where('billing_platform', $billingPlatform)
            ->where('platform_product_id', $platformProductId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * 有効な商品を全て取得
     *
     * @return CustomCollection<int, MstBillingPlatformProduct>
     */
    public function selectAllActive(): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('is_active', true)
            ->values();
    }

    /**
     * プラットフォームで絞り込んで有効な商品を取得
     *
     * @param  string  $billingPlatform  決済プラットフォーム
     * @return CustomCollection<int, MstBillingPlatformProduct>
     */
    public function selectAllActiveByBillingPlatform(string $billingPlatform): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('billing_platform', $billingPlatform)
            ->where('is_active', true)
            ->values();
    }
}
