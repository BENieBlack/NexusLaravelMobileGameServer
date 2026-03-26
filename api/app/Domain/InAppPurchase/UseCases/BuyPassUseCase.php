<?php

namespace App\Domain\InAppPurchase\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Billing\DTOs\ReceiptData;
use App\Domain\Billing\Facades\BillingFacade;
use App\Domain\InAppPurchase\Services\DiamondService;
use App\Domain\InAppPurchase\Services\PassService;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\InAppPurchase\BuyResponse;
use App\Models\Mst\MstInAppPurchase;
use App\Traits\UseCaseTrait;

/**
 * BuyPassUseCase
 * 
 * パス商品の購入ユースケース
 */
class BuyPassUseCase extends _BaseUseCase
{
    use UseCaseTrait;

    public function __construct(
        private readonly DiamondService $diamondService,
        private readonly PassService $passService,
        private readonly BillingFacade $billingFacade,
    ) {
    }

    /**
     * パス購入処理を実行
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
    public function handle(
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

        // TODO: 実際のプロダクションでは、決済プラットフォームから価格を取得する
        // ここでは仮の単価を使用
        $unitPrice = 1.0;

        // トランザクション開始
        return $this->executeWithTransaction(function () use (
            $sysPlayerId,
            $mstInAppPurchase,
            $platform,
            $billingPlatform,
            $receiptData,
            $productId,
            $unitPrice
        ) {
            // レシート検証を実行
            $verificationResult = $this->billingFacade->processPurchase(
                $sysPlayerId,
                $receiptData,
                $mstInAppPurchase->getId()
            );

            // プロダクトIDが一致するか確認
            if ($verificationResult->productId !== $productId) {
                throw new GameException(
                    GameErrorCode::PRODUCT_ID_MISMATCH,
                    'Product ID mismatch between request and receipt'
                );
            }

            $paidDiamondAmount = 0;
            $totalPaidDiamondAmount = 0;
            $totalFreeDiamondAmount = 0;

            // 1. ダイヤモンド付与（あれば）
            if ($mstInAppPurchase->getPaidDiamondAmount() > 0) {
                $result = $this->diamondService->purchaseDiamond(
                    $sysPlayerId,
                    $mstInAppPurchase,
                    $platform,
                    $billingPlatform,
                    $unitPrice
                );
                $paidDiamondAmount = $result['paid_diamond_amount'];
                $totalPaidDiamondAmount = $result['total_paid_diamond_amount'];
                $totalFreeDiamondAmount = $result['total_free_diamond_amount'];
            }

            // 2. パス効果を適用
            $this->passService->applyPassEffects($mstInAppPurchase);

            return new BuyResponse(
                paidDiamondAmount: $paidDiamondAmount,
                totalPaidDiamondAmount: $totalPaidDiamondAmount,
                totalFreeDiamondAmount: $totalFreeDiamondAmount,
                rewards: [],
            );
        });
    }
}
