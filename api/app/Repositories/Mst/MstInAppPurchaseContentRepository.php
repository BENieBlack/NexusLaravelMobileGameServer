<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstInAppPurchaseContent;
use Illuminate\Support\Collection;

/**
 * MstInAppPurchaseContentRepository
 *
 * アプリ内課金商品コンテンツマスターのRepository
 */
class MstInAppPurchaseContentRepository extends _BaseMstRepository
{
    protected string $modelClass = MstInAppPurchaseContent::class;

    /**
     * 商品IDでコンテンツを全て取得
     *
     * @param int $mstInAppPurchaseId
     * @return Collection<int, MstInAppPurchaseContent>
     */
    public function findAllByMstInAppPurchaseId(int $mstInAppPurchaseId): Collection
    {
        return $this->queryOrMemory()
            ->where('mst_in_app_purchase_id', $mstInAppPurchaseId)
            ->sortByDesc('sort_desc')
            ->values();
    }

    /**
     * 商品IDとコンテンツタイプでコンテンツを全て取得
     *
     * @param int $mstInAppPurchaseId
     * @param string $contentType コンテンツタイプ (Item, Unit, FreeDiamond)
     * @return Collection<int, MstInAppPurchaseContent>
     */
    public function findAllByMstInAppPurchaseIdAndContentType(
        int $mstInAppPurchaseId,
        string $contentType
    ): Collection {
        return $this->queryOrMemory()
            ->where('mst_in_app_purchase_id', $mstInAppPurchaseId)
            ->where('content_type', $contentType)
            ->sortByDesc('sort_desc')
            ->values();
    }
}
