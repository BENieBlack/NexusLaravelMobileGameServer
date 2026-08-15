<?php

namespace App\Domain\InAppPurchase\UseCases;

use App\Domain\InAppPurchase\Services\DiamondService;
use App\Domain\InAppPurchase\Services\InAppPurchasePassService;
use App\Domain\InAppPurchase\Services\InAppPurchaseValidationService;
use App\Http\Responses\InAppPurchase\BuyResponse;
use App\Models\Mst\MstInAppPurchase;
use NexusBilling\DataTransferObjects\Verification;
use NexusBilling\Facades\BillingFacade;

/**
 * InAppPurchaseBuyPassUseCase
 *
 * パス商品の購入ユースケース
 * _BaseBuyUseCaseを継承し、パス固有の購入処理を実装
 */
class InAppPurchaseBuyPassUseCase extends _BaseBuyUseCase
{
    public function __construct(
        InAppPurchaseValidationService $validationService,
        BillingFacade $billingFacade,
        private readonly DiamondService $diamondService,
        private readonly InAppPurchasePassService $passService,
    ) {
        parent::__construct($validationService, $billingFacade);
    }

    /**
     * {@inheritDoc}
     *
     * パス購入処理を実行
     * 1. ダイヤモンド付与（あれば）
     * 2. パス効果適用
     */
    protected function executePurchase(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        Verification $verificationDto
    ): BuyResponse {
        // TODO: 実際のプロダクションでは、決済プラットフォームから価格を取得する
        // ここでは仮の単価を使用
        $unitPrice = 1.0;

        // トランザクション内でパス購入処理を実行
        return $this->executeWithTransaction(function () use (
            $sysPlayerId,
            $mstInAppPurchase,
            $platform,
            $billingPlatform,
            $unitPrice,
            $verificationDto
        ) {
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
                    $unitPrice,
                    $verificationDto->getTransactionId()
                );
                $paidDiamondAmount = $result['paid_diamond_amount'];
                $totalPaidDiamondAmount = $result['total_paid_diamond_amount'];
                $totalFreeDiamondAmount = $result['total_free_diamond_amount'];
            }

            // 2. パス効果を適用
            $this->passService->applyPassEffects($sysPlayerId, $mstInAppPurchase);

            return new BuyResponse(
                paidDiamondAmount: $paidDiamondAmount,
                totalPaidDiamondAmount: $totalPaidDiamondAmount,
                totalFreeDiamondAmount: $totalFreeDiamondAmount,
                rewards: [],
            );
        });
    }
}
