<?php

namespace App\Domain\InAppPurchase\Services;

use App\Models\Mst\MstInAppPurchase;
use App\Repositories\Trx\TrxInAppPurchaseRepository;

/**
 * InAppPurchaseDiamondService
 *
 * ダイヤモンド購入のワークフロー全体を担当するサービス
 *
 * 責任:
 * - 購入制限チェック
 * - ダイヤモンド加算（InAppPurchaseDiamondBalanceServiceに委譲）
 * - 購入履歴更新（HistoryServiceに委譲）
 *
 * 処理フロー:
 * 1. 購入履歴を取得
 * 2. 購入制限チェック（ValidationService）
 * 3. ダイヤモンド加算（InAppPurchaseDiamondBalanceService）
 * 4. 購入履歴を更新（HistoryService）
 */
class InAppPurchaseDiamondService
{
    public function __construct(
        private readonly TrxInAppPurchaseRepository $trxInAppPurchaseRepository,
        private readonly InAppPurchaseValidationService $validationService,
        private readonly InAppPurchaseDiamondBalanceService $diamondBalanceService,
        private readonly InAppPurchaseHistoryService $historyService,
    ) {}

    /**
     * ダイヤモンド購入処理
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @param  string  $billingPlatform  決済プラットフォーム（AppStore, GooglePlay等）
     * @param  float  $unitPrice  単価（返金計算用）
     * @param  string  $transactionId  プラットフォーム固有のトランザクションID
     */
    public function purchaseDiamond(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        float $unitPrice,
        string $transactionId
    ): void {
        // 1. 購入履歴を取得（Repository経由）
        $trxInAppPurchase = $this->trxInAppPurchaseRepository->selectByBillingPlatformAndMstInAppPurchaseId(
            $billingPlatform,
            $mstInAppPurchase->getId()
        );

        // 2. 購入制限チェック
        $this->validationService->validatePurchaseLimit($mstInAppPurchase, $trxInAppPurchase, $billingPlatform);

        // 3. ダイヤモンド加算（InAppPurchaseDiamondBalanceServiceに委譲）
        $this->diamondBalanceService->addPaidDiamondWithBalance(
            $sysPlayerId,
            $platform,
            $billingPlatform,
            $mstInAppPurchase->getPaidDiamondAmount() ?? 0,
            $unitPrice
        );

        // 4. 購入履歴を更新（HistoryServiceに委譲）
        $this->historyService->updateOrCreatePurchaseHistory(
            $sysPlayerId,
            $billingPlatform,
            $mstInAppPurchase,
            $trxInAppPurchase,
            $transactionId
        );

        // 戻り値は無い。応答に載せる残高は updated_resources（trx_diamond）が持つので、
        // 残高の読み取りもしない（設計 free-diamond-item の決定 B）
    }
}
