<?php

namespace App\Domain\InAppPurchase\UseCases;

use App\Domain\InAppPurchase\Services\InAppPurchasePackService;
use App\Domain\InAppPurchase\Services\InAppPurchaseValidationService;
use App\Http\Responses\InAppPurchase\BuyResponse;
use App\Models\Mst\MstInAppPurchase;
use NexusBilling\DataTransferObjects\Verification;
use NexusBilling\Facades\BillingFacade;

/**
 * InAppPurchaseBuyPackUseCase
 *
 * パック商品の購入ユースケース
 * _BaseBuyUseCaseを継承し、パック固有の購入処理を実装
 */
class InAppPurchaseBuyPackUseCase extends _BaseBuyUseCase
{
    public function __construct(
        InAppPurchaseValidationService $validationService,
        BillingFacade $billingFacade,
        private readonly InAppPurchasePackService $packService,
    ) {
        parent::__construct($validationService, $billingFacade);
    }

    /**
     * {@inheritDoc}
     *
     * パック購入処理を実行
     */
    protected function executePurchase(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        Verification $verificationDto
    ): BuyResponse {
        // トランザクション内でパック購入処理を実行
        return $this->executeWithTransaction(function () use (
            $sysPlayerId,
            $mstInAppPurchase,
            $platform,
            $billingPlatform,
            $verificationDto
        ) {
            // パック購入処理
            $result = $this->packService->purchasePack(
                $sysPlayerId,
                $mstInAppPurchase,
                $platform,
                $billingPlatform,
                $verificationDto->getTransactionId()
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
