<?php

namespace App\Domain\InAppPurchase\UseCases;

use App\Domain\InAppPurchase\Services\InAppPurchaseDiamondService;
use App\Domain\InAppPurchase\Services\InAppPurchaseValidationService;
use App\Http\Responses\InAppPurchase\BuyResponse;
use App\Models\Mst\MstInAppPurchase;
use App\Repositories\Log\LogInAppPurchaseRepository;
use NexusBilling\DataTransferObjects\Verification;
use NexusBilling\Facades\BillingFacade;
use NexusVip\Services\VipPointService;

/**
 * BuyDiamondUseCase
 *
 * ダイヤモンド商品の購入ユースケース
 * _BaseBuyUseCaseを継承し、ダイヤモンド固有の購入処理を実装
 */
class BuyDiamondUseCase extends _BaseBuyUseCase
{
    public function __construct(
        InAppPurchaseValidationService $validationService,
        BillingFacade $billingFacade,
        VipPointService $vipPointService,
        LogInAppPurchaseRepository $logInAppPurchaseRepository,
        private readonly InAppPurchaseDiamondService $diamondService,
    ) {
        parent::__construct($validationService, $billingFacade, $vipPointService, $logInAppPurchaseRepository);
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
        Verification $verification
    ): BuyResponse {
        // 返金計算に使う購入価格。Google Playはレシート検証結果、
        // App Storeはマスターの設定値から取る
        $unitPrice = $this->resolvePurchasePrice($verification, $mstInAppPurchase, $billingPlatform);

        // トランザクションは _BaseBuyUseCase が張っている（VIP付与・課金ログと同一）
        $result = $this->diamondService->purchaseDiamond(
            $sysPlayerId,
            $mstInAppPurchase,
            $platform,
            $billingPlatform,
            $unitPrice,
            $verification->getTransactionId()
        );

        return new BuyResponse(
            paidDiamondAmount: $result['paid_diamond_amount'],
            totalPaidDiamondAmount: $result['total_paid_diamond_amount'],
            totalFreeDiamondAmount: $result['total_free_diamond_amount'],
            rewards: [],
        );
    }
}
