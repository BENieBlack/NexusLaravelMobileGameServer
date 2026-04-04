<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstInAppPurchase;
use Illuminate\Support\Collection;

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
     * @return Collection<int, MstInAppPurchase>
     */
    public function findAllActive(): Collection
    {
        return $this->queryOrMemory()
            ->where('is_active', true)
            ->sortByDesc('sort_desc')
            ->values();
    }

    /**
     * タイプで絞り込んで有効な商品を取得
     *
     * @param string $type 商品タイプ (Diamond, Pack, Pass)
     * @return Collection<int, MstInAppPurchase>
     */
    public function findAllActiveByType(string $type): Collection
    {
        return $this->queryOrMemory()
            ->where('type', $type)
            ->where('is_active', true)
            ->sortByDesc('sort_desc')
            ->values();
    }

    /**
     * IDで商品を取得（リレーション付き）
     *
     * @param int $mstInAppPurchaseId
     * @return MstInAppPurchase|null
     */
    public function findByIdWithRelations(int $mstInAppPurchaseId): ?MstInAppPurchase
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
     *
     * @param int $mstInAppPurchaseId
     * @return MstInAppPurchase|null
     */
    public function findActiveById(int $mstInAppPurchaseId): ?MstInAppPurchase
    {
        return $this->queryOrMemory()
            ->where('id', $mstInAppPurchaseId)
            ->where('is_active', true)
            ->first();
    }
}
