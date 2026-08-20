<?php

namespace App\Domain\InAppPurchase\Services;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Mst\MstBillingPlatformProduct;
use App\Models\Mst\MstInAppPurchase;
use App\Models\Trx\TrxInAppPurchase;
use Illuminate\Support\Facades\Log;
use NexusBilling\DataTransferObjects\Verification;
use NexusBilling\Validators\_BasePurchaseLimitValidator;

/**
 * InAppPurchaseValidationService
 *
 * アプリ内課金商品の購入制限・価格をチェックするサービス
 * 購入制限の判定ロジックは_BasePurchaseLimitValidatorに委譲し、
 * このクラスはゲーム固有のビジネスルールと例外処理を担当
 */
class InAppPurchaseValidationService
{
    /**
     * 購入制限チェッカー
     */
    private _BasePurchaseLimitValidator $limitValidator;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->limitValidator = new _BasePurchaseLimitValidator;
    }

    /**
     * 購入制限をチェック
     *
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  TrxInAppPurchase|null  $purchaseHistory  購入履歴（初回購入時はnull）
     * @param  string  $billingPlatform  決済プラットフォーム
     *
     * @throws GameException 購入制限に引っかかった場合
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

        // _BasePurchaseLimitValidatorで制限チェック
        $isExceeded = $this->limitValidator->isLimitExceeded(
            $mstInAppPurchase->getPurchaseLimit(),
            $purchaseHistory->purchase_count,
            $mstInAppPurchase->getPurchaseLimitReset(),
            $purchaseHistory->getPurchaseCountResetAt()
        );

        // 制限を超えている場合は例外をスロー
        if ($isExceeded) {
            // 有効なカウント数を計算（リセット判定後）
            $effectiveCount = $this->limitValidator->calculateEffectiveCount(
                $purchaseHistory->purchase_count,
                $mstInAppPurchase->getPurchaseLimitReset(),
                $purchaseHistory->getPurchaseCountResetAt()
            );

            throw new GameException(
                GameErrorCode::PURCHASE_LIMIT_EXCEEDED,
                "Purchase limit exceeded for this product. Limit: {$mstInAppPurchase->getPurchaseLimit()}, Current: {$effectiveCount}"
            );
        }
    }

    /**
     * リセットが必要な場合の新しいリセット日時を取得
     *
     * @param  string  $resetType  リセット種別（None, Daily, Weekly, Monthly）
     * @param  string|null  $lastResetAt  最終リセット日時（Y-m-d H:i:s）
     * @return string|null 新しいリセット日時（リセット不要ならnull）
     */
    public function getNewResetDateIfNeeded(
        string $resetType,
        ?string $lastResetAt
    ): ?string {
        return $this->limitValidator->getNewResetDateIfNeeded($resetType, $lastResetAt);
    }

    /**
     * 購入価格を検証
     *
     * レシート検証結果の価格とマスターデータの期待価格を照合する
     *
     * @param  Verification  $verification  レシート検証結果
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  string  $billingPlatform  決済プラットフォーム（AppStore, GooglePlay等）
     *
     * @throws GameException 価格が不一致の場合
     */
    public function validatePurchasePrice(
        Verification $verification,
        MstInAppPurchase $mstInAppPurchase,
        string $billingPlatform
    ): void {
        // 価格情報がない場合（App Storeなど）はスキップ
        if ($verification->getPriceAmountMicros() === null) {
            Log::warning('Price validation skipped: No price information in verification result', [
                'billing_platform' => $billingPlatform,
                'product_id' => $verification->getProductId(),
            ]);

            return;
        }

        // プラットフォーム商品を取得
        $platformProduct = $this->getPlatformProduct($mstInAppPurchase, $billingPlatform);

        // マスターデータに価格が設定されていない場合は警告のみ
        if ($platformProduct === null || $platformProduct->price_amount_micros === null) {
            Log::warning('Price validation skipped: No expected price in master data', [
                'billing_platform' => $billingPlatform,
                'product_id' => $verification->getProductId(),
                'actual_price_micros' => $verification->getPriceAmountMicros(),
                'actual_currency' => $verification->getPriceCurrencyCode(),
            ]);

            return;
        }

        // 価格照合
        if ($verification->getPriceAmountMicros() !== $platformProduct->price_amount_micros) {
            Log::error('Price mismatch detected', [
                'billing_platform' => $billingPlatform,
                'product_id' => $verification->getProductId(),
                'expected_price_micros' => $platformProduct->price_amount_micros,
                'actual_price_micros' => $verification->getPriceAmountMicros(),
                'expected_currency' => $platformProduct->price_currency_code,
                'actual_currency' => $verification->getPriceCurrencyCode(),
            ]);

            throw new GameException(
                GameErrorCode::PRICE_MISMATCH,
                'Purchase price does not match expected price'
            );
        }

        // 通貨コード照合
        if ($platformProduct->price_currency_code !== null
            && $verification->getPriceCurrencyCode() !== $platformProduct->price_currency_code) {
            Log::error('Currency mismatch detected', [
                'billing_platform' => $billingPlatform,
                'product_id' => $verification->getProductId(),
                'expected_currency' => $platformProduct->price_currency_code,
                'actual_currency' => $verification->getPriceCurrencyCode(),
            ]);

            throw new GameException(
                GameErrorCode::PRICE_MISMATCH,
                'Purchase currency does not match expected currency'
            );
        }

        Log::info('Price validation passed', [
            'billing_platform' => $billingPlatform,
            'product_id' => $verification->getProductId(),
            'price_micros' => $verification->getPriceAmountMicros(),
            'currency' => $verification->getPriceCurrencyCode(),
        ]);
    }

    /**
     * 購入価格を解決する（通貨単位）
     *
     * 返金計算のため、実際に支払われた金額を返す。
     * Google Playはレシート検証結果に価格が含まれる。
     * App Storeは /verifyReceipt が価格を返さないため、マスターの設定値を使う。
     *
     * どちらも得られない場合は0.0を返す（金額不明のまま固定値を入れない）。
     *
     * @param  Verification  $verification  レシート検証結果
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  string  $billingPlatform  決済プラットフォーム（AppStore, GooglePlay等）
     */
    public function resolvePurchasePrice(
        Verification $verification,
        MstInAppPurchase $mstInAppPurchase,
        string $billingPlatform
    ): float {
        $priceMicros = $verification->getPriceAmountMicros()
            ?? $this->getPlatformProduct($mstInAppPurchase, $billingPlatform)?->price_amount_micros;

        if ($priceMicros === null) {
            Log::warning('Purchase price is unknown', [
                'billing_platform' => $billingPlatform,
                'product_id' => $verification->getProductId(),
            ]);

            return 0.0;
        }

        return (int) $priceMicros / 1_000_000;
    }

    /**
     * プラットフォーム商品を取得
     *
     * @param  MstInAppPurchase  $mstInAppPurchase  商品マスター
     * @param  string  $billingPlatform  決済プラットフォーム
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
