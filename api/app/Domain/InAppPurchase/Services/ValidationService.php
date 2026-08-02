<?php

namespace App\Domain\InAppPurchase\Services;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Mst\MstBillingPlatformProduct;
use App\Models\Mst\MstInAppPurchase;
use App\Models\Trx\TrxInAppPurchase;
use Illuminate\Support\Facades\Log;
use LaravelMobileBilling\DTOs\VerificationDto;
use NexusUtilities\ClockUtility;

/**
 * ValidationService
 * 
 * アプリ内課金商品の購入制限をチェックするサービス
 */
class ValidationService
{
    /**
     * 購入制限をチェック
     * 
     * @param MstInAppPurchase $mstInAppPurchase 商品マスター
     * @param TrxInAppPurchase|null $purchaseHistory 購入履歴（初回購入時はnull）
     * @param string $billingPlatform 決済プラットフォーム
     * @throws GameException 購入制限に引っかかった場合
     * @return void
     */
    public function validatePurchaseLimit(
        MstInAppPurchase $mstInAppPurchase,
        ?TrxInAppPurchase $purchaseHistory,
        string $billingPlatform
    ): void {
        // 購入制限がない場合はチェック不要
        if ($mstInAppPurchase->getPurchaseLimit() === null) {
            return;
        }

        // 初回購入の場合は問題なし
        if ($purchaseHistory === null) {
            return;
        }

        // リセットが必要かチェック
        $shouldReset = $this->shouldResetPurchaseCount(
            $mstInAppPurchase->getPurchaseLimitReset(),
            $purchaseHistory->getPurchaseCountResetAt()
        );

        // リセットが必要な場合は、purchase_countをリセット後としてカウント
        $currentCount = $shouldReset ? 0 : $purchaseHistory->purchase_count;

        // 購入制限チェック
        if ($currentCount >= $mstInAppPurchase->getPurchaseLimit()) {
            throw new GameException(
                GameErrorCode::PURCHASE_LIMIT_EXCEEDED,
                "Purchase limit exceeded for this product. Limit: {$mstInAppPurchase->getPurchaseLimit()}, Current: {$currentCount}"
            );
        }
    }

    /**
     * 購入回数をリセットすべきかチェック
     * 
     * @param string $resetType リセット種別（None, Daily, Weekly, Monthly）
     * @param \DateTimeInterface|null $lastResetAt 最終リセット日時
     * @return bool リセットが必要な場合true
     */
    private function shouldResetPurchaseCount(
        string $resetType,
        ?\DateTimeInterface $lastResetAt
    ): bool {
        if ($resetType === 'None' || $lastResetAt === null) {
            return false;
        }

        $now = ClockUtility::now();

        return match ($resetType) {
            'Daily' => !ClockUtility::isToday($lastResetAt),
            'Weekly' => $now->weekOfYear !== ClockUtility::weekOfYear($lastResetAt) || $now->year !== ClockUtility::year($lastResetAt),
            'Monthly' => $now->month !== ClockUtility::month($lastResetAt) || $now->year !== ClockUtility::year($lastResetAt),
            default => false,
        };
    }

    /**
     * リセットが必要な場合の新しいリセット日時を取得
     * 
     * @param string $resetType リセット種別（None, Daily, Weekly, Monthly）
     * @param \DateTimeInterface|null $lastResetAt 最終リセット日時
     * @return \DateTimeInterface|null 新しいリセット日時（リセット不要ならnull）
     */
    public function getNewResetDateIfNeeded(
        string $resetType,
        ?\DateTimeInterface $lastResetAt
    ): ?\DateTimeInterface {
        if ($this->shouldResetPurchaseCount($resetType, $lastResetAt)) {
            return ClockUtility::now();
        }

        return null;
    }

    /**
     * 購入価格を検証
     * 
     * レシート検証結果の価格とマスターデータの期待価格を照合する
     * 
     * @param VerificationDto $verificationResult レシート検証結果
     * @param MstInAppPurchase $mstInAppPurchase 商品マスター
     * @param string $billingPlatform 決済プラットフォーム（AppStore, GooglePlay等）
     * @throws GameException 価格が不一致の場合
     * @return void
     */
    public function validatePurchasePrice(
        VerificationDto $verificationResult,
        MstInAppPurchase $mstInAppPurchase,
        string $billingPlatform
    ): void {
        // 価格情報がない場合（App Storeなど）はスキップ
        if ($verificationResult->priceAmountMicros === null) {
            Log::warning('Price validation skipped: No price information in verification result', [
                'billing_platform' => $billingPlatform,
                'product_id' => $verificationResult->productId,
            ]);
            return;
        }

        // プラットフォーム商品を取得
        $platformProduct = $this->getPlatformProduct($mstInAppPurchase, $billingPlatform);
        
        // マスターデータに価格が設定されていない場合は警告のみ
        if ($platformProduct === null || $platformProduct->price_amount_micros === null) {
            Log::warning('Price validation skipped: No expected price in master data', [
                'billing_platform' => $billingPlatform,
                'product_id' => $verificationResult->productId,
                'actual_price_micros' => $verificationResult->priceAmountMicros,
                'actual_currency' => $verificationResult->priceCurrencyCode,
            ]);
            return;
        }

        // 価格照合
        if ($verificationResult->priceAmountMicros !== $platformProduct->price_amount_micros) {
            Log::error('Price mismatch detected', [
                'billing_platform' => $billingPlatform,
                'product_id' => $verificationResult->productId,
                'expected_price_micros' => $platformProduct->price_amount_micros,
                'actual_price_micros' => $verificationResult->priceAmountMicros,
                'expected_currency' => $platformProduct->price_currency_code,
                'actual_currency' => $verificationResult->priceCurrencyCode,
            ]);

            throw new GameException(
                GameErrorCode::PRICE_MISMATCH,
                'Purchase price does not match expected price'
            );
        }

        // 通貨コード照合
        if ($platformProduct->price_currency_code !== null 
            && $verificationResult->priceCurrencyCode !== $platformProduct->price_currency_code) {
            Log::error('Currency mismatch detected', [
                'billing_platform' => $billingPlatform,
                'product_id' => $verificationResult->productId,
                'expected_currency' => $platformProduct->price_currency_code,
                'actual_currency' => $verificationResult->priceCurrencyCode,
            ]);

            throw new GameException(
                GameErrorCode::PRICE_MISMATCH,
                'Purchase currency does not match expected currency'
            );
        }

        Log::info('Price validation passed', [
            'billing_platform' => $billingPlatform,
            'product_id' => $verificationResult->productId,
            'price_micros' => $verificationResult->priceAmountMicros,
            'currency' => $verificationResult->priceCurrencyCode,
        ]);
    }

    /**
     * プラットフォーム商品を取得
     * 
     * @param MstInAppPurchase $mstInAppPurchase 商品マスター
     * @param string $billingPlatform 決済プラットフォーム
     * @return MstBillingPlatformProduct|null
     */
    private function getPlatformProduct(
        MstInAppPurchase $mstInAppPurchase,
        string $billingPlatform
    ): ?MstBillingPlatformProduct {
        return match ($billingPlatform) {
            'AppStore' => $mstInAppPurchase->appStoreProduct,
            'GooglePlay' => $mstInAppPurchase->googlePlayProduct,
            default => null,
        };
    }
}
