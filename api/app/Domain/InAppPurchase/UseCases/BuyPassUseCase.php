<?php

namespace App\Domain\InAppPurchase\UseCases;

use App\Domain\InAppPurchase\Responses\BuyResponse;
use App\Domain\InAppPurchase\Services\InAppPurchaseDiamondService;
use App\Domain\InAppPurchase\Services\InAppPurchasePassService;
use App\Domain\InAppPurchase\Services\InAppPurchaseValidationService;
use App\Models\Mst\MstInAppPurchase;
use App\Repositories\Log\LogInAppPurchaseRepository;
use NexusBilling\DataTransferObjects\Verification;
use NexusBilling\Facades\BillingFacade;
use NexusVip\Services\VipPointService;

/**
 * BuyPassUseCase
 *
 * パス商品の購入ユースケース
 * _BaseBuyUseCaseを継承し、パス固有の購入処理を実装
 */
class BuyPassUseCase extends _BaseBuyUseCase
{
    public function __construct(
        InAppPurchaseValidationService $validationService,
        BillingFacade $billingFacade,
        VipPointService $vipPointService,
        LogInAppPurchaseRepository $logInAppPurchaseRepository,
        private readonly InAppPurchaseDiamondService $diamondService,
        private readonly InAppPurchasePassService $passService,
    ) {
        parent::__construct($validationService, $billingFacade, $vipPointService, $logInAppPurchaseRepository);
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
        Verification $verification
    ): BuyResponse {
        // 返金計算に使う購入価格。Google Playはレシート検証結果、
        // App Storeはマスターの設定値から取る
        $unitPrice = $this->resolvePurchasePrice($verification, $mstInAppPurchase, $billingPlatform);

        // トランザクションは _BaseBuyUseCase が張っている（VIP付与・課金ログと同一）
        // 1. ダイヤモンド付与（あれば）
        if ($mstInAppPurchase->getPaidDiamondAmount() > 0) {
            $this->diamondService->purchaseDiamond(
                $sysPlayerId,
                $mstInAppPurchase,
                $platform,
                $billingPlatform,
                $unitPrice,
                $verification->getTransactionId()
            );
        }

        // 2. パス効果を適用
        $this->passService->applyPassEffects($sysPlayerId, $mstInAppPurchase);

        // 応答は updated_resources だけ（有償の加算は trx_diamond で分かる）
        return new BuyResponse;
    }
}
