<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstBillingPlatformProduct;
use Illuminate\Support\Collection;

/**
 * MstBillingPlatformProductRepository
 *
 * プラットフォーム課金商品マスターのRepository
 */
class MstBillingPlatformProductRepository extends _BaseMstRepository
{
    protected string $modelClass = MstBillingPlatformProduct::class;

    /**
     * プラットフォームと商品IDで課金商品を取得
     *
     * @param string $billingPlatform 決済プラットフォーム
     * @param string $platformProductId プラットフォーム商品ID
     * @return MstBillingPlatformProduct|null
     */
    public function findByBillingPlatformAndPlatformProductId(
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
     * @return Collection<int, MstBillingPlatformProduct>
     */
    public function findAllActive(): Collection
    {
        return $this->queryOrMemory()
            ->where('is_active', true)
            ->values();
    }

    /**
     * プラットフォームで絞り込んで有効な商品を取得
     *
     * @param string $billingPlatform 決済プラットフォーム
     * @return Collection<int, MstBillingPlatformProduct>
     */
    public function findAllActiveByBillingPlatform(string $billingPlatform): Collection
    {
        return $this->queryOrMemory()
            ->where('billing_platform', $billingPlatform)
            ->where('is_active', true)
            ->values();
    }
}
