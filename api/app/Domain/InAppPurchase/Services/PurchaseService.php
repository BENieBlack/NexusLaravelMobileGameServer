<?php

namespace App\Domain\InAppPurchase\Services;

use App\Models\Mst\MstInAppPurchase;
use App\Repositories\Trx\TrxInAppPurchaseRepository;

/**
 * PurchaseService
 * 
 * ダイヤモンド購入のワークフロー全体を担当するサービス
 * 
 * 責任:
 * - 購入制限チェック
 * - ダイヤモンド加算（DiamondBalanceServiceに委譲）
 * - 購入履歴更新（HistoryServiceに委譲）
 * 
 * 処理フロー:
 * 1. 購入履歴を取得
 * 2. 購入制限チェック（ValidationService）
 * 3. ダイヤモンド加算（DiamondBalanceService）
 * 4. 購入履歴を更新（HistoryService）
 */
class PurchaseService
{
    public function __construct(
        private readonly TrxInAppPurchaseRepository $trxInAppPurchaseRepository,
        private readonly ValidationService $validationService,
        private readonly DiamondBalanceService $diamondBalanceService,
        private readonly HistoryService $historyService,
    ) {
    }

    /**
     * ダイヤモンド購入処理
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param MstInAppPurchase $mstInAppPurchase 商品マスター
     * @param string $platform プラットフォーム（Apple, Google）
     * @param string $billingPlatform 決済プラットフォーム（AppStore, GooglePlay等）
     * @param float $unitPrice 単価（返金計算用）
     * @param string $transactionId プラットフォーム固有のトランザクションID
     * @return array{
     *   paid_diamond_amount: int,
     *   total_paid_diamond_amount: int,
     *   total_free_diamond_amount: int
     * }
     */
    public function purchaseDiamond(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        float $unitPrice,
        string $transactionId
    ): array {
        // 1. 購入履歴を取得（Repository経由）
        $purchaseHistory = $this->trxInAppPurchaseRepository->selectByBillingPlatformAndMstInAppPurchaseId(
            $billingPlatform,
            $mstInAppPurchase->getId()
        );

        // 2. 購入制限チェック
        $this->validationService->validatePurchaseLimit($mstInAppPurchase, $purchaseHistory, $billingPlatform);

        // 3. ダイヤモンド加算（DiamondBalanceServiceに委譲）
        $this->diamondBalanceService->addPaidDiamondWithBalance(
            $sysPlayerId,
            $platform,
            $billingPlatform,
            $mstInAppPurchase->getPaidDiamondAmount(),
            $unitPrice
        );

        // 4. 購入履歴を更新（HistoryServiceに委譲）
        $this->historyService->updateOrCreatePurchaseHistory(
            $sysPlayerId,
            $billingPlatform,
            $mstInAppPurchase,
            $purchaseHistory,
            $transactionId
        );

        // 5. 更新後の残高を取得して返す
        $balance = $this->diamondBalanceService->getBalance($sysPlayerId, $platform);

        return [
            'paid_diamond_amount' => $mstInAppPurchase->getPaidDiamondAmount(),
            'total_paid_diamond_amount' => $balance['paid_amount'],
            'total_free_diamond_amount' => $balance['free_amount'],
        ];
    }
}
