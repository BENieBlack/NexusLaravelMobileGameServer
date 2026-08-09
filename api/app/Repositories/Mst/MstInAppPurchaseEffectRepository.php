<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstInAppPurchaseEffect;
use Nexus\Core\Support\CustomCollection;

/**
 * MstInAppPurchaseEffectRepository
 *
 * アプリ内課金商品効果マスターのRepository
 *
 * @extends _BaseMstRepository<MstInAppPurchaseEffect>
 */
class MstInAppPurchaseEffectRepository extends _BaseMstRepository
{
    protected string $modelClass = MstInAppPurchaseEffect::class;

    /**
     * 商品IDで効果を全て取得
     *
     * @return CustomCollection<int, MstInAppPurchaseEffect>
     */
    public function findAllByMstInAppPurchaseId(int $mstInAppPurchaseId): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('mst_in_app_purchase_id', $mstInAppPurchaseId)
            ->values();
    }

    /**
     * 商品IDと効果タイプで効果を取得
     *
     * @param  string  $effectType  効果タイプ
     */
    public function findByMstInAppPurchaseIdAndEffectType(
        int $mstInAppPurchaseId,
        string $effectType
    ): ?MstInAppPurchaseEffect {
        return $this->queryOrMemory()
            ->where('mst_in_app_purchase_id', $mstInAppPurchaseId)
            ->where('effect_type', $effectType)
            ->first();
    }
}
