<?php

namespace App\Domain\InAppPurchase\UseCases;

use App\Domain\InAppPurchase\Services\InAppPurchasePackService;
use App\Domain\InAppPurchase\Services\InAppPurchaseValidationService;
use App\Http\Responses\InAppPurchase\BuyResponse;
use App\Models\Mst\MstInAppPurchase;
use App\Repositories\Log\LogInAppPurchaseRepository;
use NexusBilling\DataTransferObjects\Verification;
use NexusBilling\Facades\BillingFacade;
use NexusVip\Services\VipPointService;

/**
 * BuyPackUseCase
 *
 * パック商品の購入ユースケース
 * _BaseBuyUseCaseを継承し、パック固有の購入処理を実装
 */
class BuyPackUseCase extends _BaseBuyUseCase
{
    public function __construct(
        InAppPurchaseValidationService $validationService,
        BillingFacade $billingFacade,
        VipPointService $vipPointService,
        LogInAppPurchaseRepository $logInAppPurchaseRepository,
        private readonly InAppPurchasePackService $packService,
    ) {
        parent::__construct($validationService, $billingFacade, $vipPointService, $logInAppPurchaseRepository);
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
        Verification $verification
    ): BuyResponse {
        // トランザクションは _BaseBuyUseCase が張っている（VIP付与・課金ログと同一）
        $result = $this->packService->purchasePack(
            $sysPlayerId,
            $mstInAppPurchase,
            $platform,
            $billingPlatform,
            $verification->getTransactionId()
        );

        return new BuyResponse(
            paidDiamondAmount: 0,
            totalPaidDiamondAmount: 0,
            totalFreeDiamondAmount: $result['total_free_diamond_amount'],
            rewards: $result['contents'],
        );
    }
}
