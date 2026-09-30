<?php

namespace App\Domain\InAppPurchase\Services;

use App\Models\Mst\MstInAppPurchase;
use App\Models\Trx\TrxInAppPurchase;
use App\Repositories\Trx\TrxInAppPurchaseRepository;
use Nexus\Core\Utilities\ClockUtility;

/**
 * InAppPurchaseHistoryService
 *
 * アプリ内課金の購入履歴を管理するサービス
 * DiamondServiceとPackServiceで共通の購入履歴更新ロジックを提供
 */
class InAppPurchaseHistoryService
{
    public function __construct(
        private readonly TrxInAppPurchaseRepository $trxInAppPurchaseRepository,
        private readonly InAppPurchaseValidationService $validationService,
    ) {}

    /**
     * 購入履歴を更新または作成
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $billingPlatform  決済プラットフォーム（AppStore, GooglePlay等）
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  TrxInAppPurchase|null  $purchaseHistory  既存の購入履歴（存在する場合）
     * @param  string  $transactionId  プラットフォーム固有のトランザクションID
     */
    public function updateOrCreatePurchaseHistory(
        int $sysPlayerId,
        string $billingPlatform,
        MstInAppPurchase $mstInAppPurchase,
        ?TrxInAppPurchase $purchaseHistory,
        string $transactionId
    ): void {
        if ($purchaseHistory === null) {
            // 初回購入の場合は新規作成
            $purchaseHistory = new TrxInAppPurchase([
                'sys_player_id' => $sysPlayerId,
                'billing_platform' => $billingPlatform,
                'mst_in_app_purchase_id' => $mstInAppPurchase->getId(),
                'transaction_id' => $transactionId,
                'total_purchase_count' => 1,
                'purchase_count' => 1,
                'purchase_count_reset_at' => $mstInAppPurchase->getPurchaseLimitReset() !== 'none' ? ClockUtility::now() : null,
            ]);
            $this->trxInAppPurchaseRepository->setModel($purchaseHistory);

            return;
        }

        // リセットが必要かチェック
        $newResetDate = $this->validationService->getNewResetDateIfNeeded(
            $mstInAppPurchase->getPurchaseLimitReset(),
            $purchaseHistory->getPurchaseCountResetAt()
        );

        if ($newResetDate !== null) {
            // リセットが必要な場合
            $purchaseHistory->setPurchaseCount(1);
            $purchaseHistory->setPurchaseCountResetAt($newResetDate);
        } else {
            // リセット不要の場合
            $purchaseHistory->setPurchaseCount($purchaseHistory->getPurchaseCount() + 1);
        }

        $purchaseHistory->setTotalPurchaseCount($purchaseHistory->getTotalPurchaseCount() + 1);
        $this->trxInAppPurchaseRepository->setModel($purchaseHistory);
    }
}
