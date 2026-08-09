<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxInAppPurchaseEffect;
use NexusPersistence\Support\CustomCollection;

/**
 * TrxInAppPurchaseEffectRepository
 *
 * Pass課金効果の管理を行うリポジトリ
 * データアクセスのみを担当し、ビジネスロジックはServiceに委譲
 *
 * @extends _BaseTrxRepository<TrxInAppPurchaseEffect>
 */
class TrxInAppPurchaseEffectRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxInAppPurchaseEffect::class;

    /**
     * プレイヤーの特定商品の効果を全て取得（有効・無効問わず）
     *
     * @return CustomCollection<string, TrxInAppPurchaseEffect>
     */
    public function selectAllEffectsByMstInAppPurchaseId(int $mstInAppPurchaseId): CustomCollection
    {
        $sysPlayerId = $this->getSysPlayerId();
        $effectCollection = $this->getMapBySysPlayerId($sysPlayerId);

        return $effectCollection->where('mst_in_app_purchase_id', $mstInAppPurchaseId);
    }
}
