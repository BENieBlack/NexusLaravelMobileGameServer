<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstInAppPurchase;
use Nexus\Core\Support\CustomCollection;

/**
 * MstInAppPurchaseRepository
 *
 * アプリ内課金商品マスターのRepository
 *
 * @extends _BaseMstRepository<MstInAppPurchase>
 */
class MstInAppPurchaseRepository extends _BaseMstRepository
{
    protected string $modelClass = MstInAppPurchase::class;

    /**
     * 有効な商品を全て取得
     *
     * @return CustomCollection<int, MstInAppPurchase>
     */
    public function selectAllActive(): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('is_active', true)
            ->sortByDesc('sort_desc')
            ->values();
    }

    /**
     * タイプで絞り込んで有効な商品を取得
     *
     * @param  string  $type  商品タイプ (Diamond, Pack, Pass)
     * @return CustomCollection<int, MstInAppPurchase>
     */
    public function selectAllActiveByType(string $type): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('type', $type)
            ->where('is_active', true)
            ->sortByDesc('sort_desc')
            ->values();
    }

    /**
     * IDで商品を取得（リレーション付き）
     */
    public function selectByIdWithRelations(int $mstInAppPurchaseId): ?MstInAppPurchase
    {
        $product = $this->selectById($mstInAppPurchaseId);

        if ($product === null) {
            return null;
        }

        // リレーションをロード（contents, effects）
        $product->load(['contents', 'effects']);

        return $product;
    }

    /**
     * IDとアクティブ状態で商品を取得
     */
    public function selectActiveById(int $mstInAppPurchaseId): ?MstInAppPurchase
    {
        return $this->queryOrMemory()
            ->where('is_active', true)
            ->firstWhere('id', $mstInAppPurchaseId);
    }
}
