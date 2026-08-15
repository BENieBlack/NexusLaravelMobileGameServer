<?php

namespace App\Domain\InAppPurchase\UseCases;

use App\Domain\InAppPurchase\Services\DiamondService;
use App\Domain\InAppPurchase\Services\InAppPurchaseValidationService;
use App\Http\Responses\InAppPurchase\BuyResponse;
use App\Models\Mst\MstInAppPurchase;
use NexusBilling\DataTransferObjects\Verification;
use NexusBilling\Facades\BillingFacade;

/**
 * InAppPurchaseBuyDiamondUseCase
 *
 * ダイヤモンド商品の購入ユースケース
 * _BaseBuyUseCaseを継承し、ダイヤモンド固有の購入処理を実装
 */
class InAppPurchaseBuyDiamondUseCase extends _BaseBuyUseCase
{
    public function __construct(
        InAppPurchaseValidationService $validationService,
        BillingFacade $billingFacade,
        private readonly DiamondService $diamondService,
    ) {
        parent::__construct($validationService, $billingFacade);
    }

    /**
     * {@inheritDoc}
     *
     * ダイヤモンド購入処理を実行
     */
    protected function executePurchase(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        Verification $verificationDto
    ): BuyResponse {
        // TODO: 実際のプロダクションでは、決済プラットフォームから価格を取得する
        // ここでは仮の単価を使用（ダイヤ1個あたりの価格）
        $unitPrice = 1.0;

        // トランザクション内でダイヤモンド購入処理を実行
        return $this->executeWithTransaction(function () use (
            $sysPlayerId,
            $mstInAppPurchase,
            $platform,
            $billingPlatform,
            $unitPrice,
            $verificationDto
        ) {
            // ダイヤモンド購入処理
            $result = $this->diamondService->purchaseDiamond(
                $sysPlayerId,
                $mstInAppPurchase,
                $platform,
                $billingPlatform,
                $unitPrice,
                $verificationDto->getTransactionId()
            );

            return new BuyResponse(
                paidDiamondAmount: $result['paid_diamond_amount'],
                totalPaidDiamondAmount: $result['total_paid_diamond_amount'],
                totalFreeDiamondAmount: $result['total_free_diamond_amount'],
                rewards: [],
            );
        });
    }
}
