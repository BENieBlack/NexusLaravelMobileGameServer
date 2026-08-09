<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstInAppPurchaseContent;
use Nexus\Core\Support\CustomCollection;

/**
 * MstInAppPurchaseContentRepository
 *
 * アプリ内課金商品コンテンツマスターのRepository
 *
 * @extends _BaseMstRepository<MstInAppPurchaseContent>
 */
class MstInAppPurchaseContentRepository extends _BaseMstRepository
{
    protected string $modelClass = MstInAppPurchaseContent::class;

    /**
     * 商品IDでコンテンツを全て取得
     *
     * @return CustomCollection<int, MstInAppPurchaseContent>
     */
    public function findAllByMstInAppPurchaseId(int $mstInAppPurchaseId): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('mst_in_app_purchase_id', $mstInAppPurchaseId)
            ->sortByDesc('sort_desc')
            ->values();
    }

    /**
     * 商品IDとコンテンツタイプでコンテンツを全て取得
     *
     * @param  string  $contentType  コンテンツタイプ (Item, Unit, FreeDiamond)
     * @return CustomCollection<int, MstInAppPurchaseContent>
     */
    public function findAllByMstInAppPurchaseIdAndContentType(
        int $mstInAppPurchaseId,
        string $contentType
    ): CustomCollection {
        return $this->queryOrMemory()
            ->where('mst_in_app_purchase_id', $mstInAppPurchaseId)
            ->where('content_type', $contentType)
            ->sortByDesc('sort_desc')
            ->values();
    }
}
