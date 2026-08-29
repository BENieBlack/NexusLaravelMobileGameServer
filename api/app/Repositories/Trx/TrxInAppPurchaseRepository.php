<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxInAppPurchase;

/**
 * TrxInAppPurchaseRepository
 *
 * 課金購入履歴管理Repository
 * 複合主キー: (sys_player_id, mst_in_app_purchase_id)
 *
 * @extends _BaseTrxRepository<TrxInAppPurchase>
 */
class TrxInAppPurchaseRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxInAppPurchase::class;

    /**
     * プレイヤーID、決済プラットフォーム、商品IDで購入履歴を取得
     *
     * @param  string  $billingPlatform  決済プラットフォーム
     * @param  int  $mstInAppPurchaseId  商品ID
     */
    public function selectByBillingPlatformAndMstInAppPurchaseId(string $billingPlatform, int $mstInAppPurchaseId): ?TrxInAppPurchase
    {
        $sysPlayerId = $this->getSysPlayerId();

        // メモリ内キューから検索
        $queue = $this->queryOrMemory();
        $found = $queue->first(function ($model) use ($sysPlayerId, $billingPlatform, $mstInAppPurchaseId) {
            return $model->sys_player_id === $sysPlayerId
                && $model->billing_platform === $billingPlatform
                && $model->mst_in_app_purchase_id === $mstInAppPurchaseId;
        });

        if ($found) {
            return $found;
        }

        // DBから検索
        return TrxInAppPurchase::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('billing_platform', $billingPlatform)
            ->where('mst_in_app_purchase_id', $mstInAppPurchaseId)
            ->first();
    }
}
