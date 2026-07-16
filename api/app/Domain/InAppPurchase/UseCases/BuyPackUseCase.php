<?php

namespace App\Domain\InAppPurchase\UseCases;

use App\Domain\_BaseUseCase;
use LaravelMobileBilling\DTOs\ReceiptData;
use LaravelMobileBilling\Facades\BillingFacade;
use App\Domain\InAppPurchase\Services\PackService;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\InAppPurchase\BuyResponse;
use App\Models\Mst\MstInAppPurchase;

/**
 * BuyPackUseCase
 * 
 * パック商品の購入ユースケース
 */
class BuyPackUseCase extends _BaseUseCase
{

    public function __construct(
        private readonly PackService $packService,
        private readonly BillingFacade $billingFacade,
    ) {
    }

    /**
     * パック購入処理を実行
     *
     * @param int $sysPlayerId プレイヤーID（Controllerで認証済み）
     * @param MstInAppPurchase $mstInAppPurchase 商品マスター（Controllerで検証済み）
     * @param string $platform プラットフォーム（Apple, Google）
     * @param string $billingPlatform 決済プラットフォーム（AppStore, GooglePlay, PayPal, Stripe）
     * @param string $receipt レシート文字列
     * @param string|null $transactionId トランザクションID
     * @param string $productId プロダクトID
     * @return BuyResponse
     * @throws GameException
     */
    public function exec(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        string $receipt,
        ?string $transactionId,
        string $productId
    ): BuyResponse {
        // レシートデータを作成
        $receiptData = new ReceiptData(
            playerId: $sysPlayerId,
            billingPlatform: $billingPlatform,
            receipt: $billingPlatform === 'AppStore' ? $receipt : null,
            purchaseToken: $billingPlatform === 'GooglePlay' ? $receipt : null,
            productId: $productId,
            transactionId: $transactionId
        );

        // トランザクション開始
        return $this->executeWithTransaction(function () use (
            $sysPlayerId,
            $mstInAppPurchase,
            $platform,
            $billingPlatform,
            $receiptData,
            $productId
        ) {
            // レシート検証を実行
            // 一意なリクエストIDを生成（重複防止用）
            $uniqueRequestId = $sysPlayerId . '_' . $mstInAppPurchase->getId() . '_' . ($receiptData->transactionId ?? time());
            
            $verificationResult = $this->billingFacade->processPurchase(
                billingPlatform: $billingPlatform,
                receiptData: $receiptData,
                uniqueRequestId: $uniqueRequestId
            );

            // プロダクトIDが一致するか確認
            if ($verificationResult->productId !== $productId) {
                throw new GameException(
                    GameErrorCode::PRODUCT_ID_MISMATCH,
                    'Product ID mismatch between request and receipt'
                );
            }

            // パック購入処理
            $result = $this->packService->purchasePack(
                $sysPlayerId,
                $mstInAppPurchase,
                $platform,
                $billingPlatform
            );

            return new BuyResponse(
                paidDiamondAmount: 0,
                totalPaidDiamondAmount: 0,
                totalFreeDiamondAmount: $result['total_free_diamond_amount'],
                rewards: $result['contents'],
            );
        });
    }
}
