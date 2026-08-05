<?php

namespace App\Domain\InAppPurchase\Services;

use App\Models\Mst\MstInAppPurchase;
use App\Domain\InAppPurchase\Services\InAppPurchasePurchaseService;

/**
 * DiamondService (Facade)
 * 
 * ダイヤモンド関連の操作を提供する Facade
 * 
 * 既存コードとの互換性のため、このクラスを残します。
 * 内部では新しいServiceに処理を委譲します。
 * 
 * 新規コードでは、以下のServiceを直接使用することを推奨:
 * - DiamondBalanceService: 残高管理
 * - PurchaseService: 購入処理
 * 
 * @deprecated 新規コードでは DiamondBalanceService または PurchaseService を使用してください
 */
class DiamondService
{
    public function __construct(
        private readonly DiamondBalanceService $diamondBalanceService,
        private readonly InAppPurchasePurchaseService $diamondPurchaseService,
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
     * @return array{paid_diamond_amount: int, total_paid_diamond_amount: int, total_free_diamond_amount: int}
     * 
     * @deprecated PurchaseService::purchaseDiamond() を使用してください
     */
    public function purchaseDiamond(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,
        string $platform,
        string $billingPlatform,
        float $unitPrice,
        string $transactionId
    ): array {
        return $this->diamondPurchaseService->purchaseDiamond(
            $sysPlayerId,
            $mstInAppPurchase,
            $platform,
            $billingPlatform,
            $unitPrice,
            $transactionId
        );
    }

    /**
     * ダイヤモンドを加算（有償/無償）
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $platform プラットフォーム（Apple, Google）
     * @param int $amount 加算する数量
     * @param bool $isPaid 有償ダイヤモンドか（falseの場合は無償）
     * @return void
     * 
     * @deprecated DiamondBalanceService::addDiamond() を使用してください
     */
    public function addDiamond(int $sysPlayerId, string $platform, int $amount, bool $isPaid = false): void
    {
        $this->diamondBalanceService->addDiamond($sysPlayerId, $platform, $amount, $isPaid);
    }

    /**
     * ダイヤモンドを消費（無償 → 有償の順で消費、または有償のみ）
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param int $amount 消費する数量
     * @param bool $isPaidOnly 有償ダイヤのみを消費するか（falseの場合は無償→有償の順）
     * @return void
     * @throws \Exception 残高不足の場合
     * 
     * @deprecated DiamondBalanceService::consumeDiamond() を使用してください
     */
    public function consumeDiamond(int $sysPlayerId, int $amount, bool $isPaidOnly = false): void
    {
        $this->diamondBalanceService->consumeDiamond($sysPlayerId, $amount, $isPaidOnly);
    }
}
