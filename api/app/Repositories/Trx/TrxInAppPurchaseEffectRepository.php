<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxInAppPurchaseEffect;
use Illuminate\Support\Collection;

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
     * @param int $mstInAppPurchaseId
     * @return Collection<string, TrxInAppPurchaseEffect>
     */
    public function findAllEffectsByMstInAppPurchaseId(int $mstInAppPurchaseId): Collection
    {
        $sysPlayerId = $this->getSysPlayerId();
        $effectCollection = $this->getMapBySysPlayerId($sysPlayerId);

        return $effectCollection->where('mst_in_app_purchase_id', $mstInAppPurchaseId);
    }
}
