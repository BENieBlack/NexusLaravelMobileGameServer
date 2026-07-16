<?php

namespace LaravelMobileBilling\Facades;

use LaravelMobileBilling\DTOs\ReceiptData;
use LaravelMobileBilling\DTOs\SubscriptionStatus;
use LaravelMobileBilling\DTOs\VerificationResult;
use LaravelMobileBilling\Exceptions\DuplicatePurchaseException;
use LaravelMobileBilling\Services\BillingPlatformFactory;
use LaravelMobileBilling\Services\IdempotencyService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Billing ファサード
 * 
 * 外部から呼ばれる統一API
 * プラットフォーム固有の実装を抽象化し、統一的なインターフェースを提供
 */
class BillingFacade
{
    public function __construct(
        private readonly BillingPlatformFactory $platformFactory,
        private readonly IdempotencyService $idempotencyService,
    ) {}

    /**
     * 購入処理（レシート検証）
     * 
     * 外部から呼ばれるメインAPI
     * どのプラットフォームでも同じように使える統一インターフェース
     * 
     * @param string $billingPlatform 決済プラットフォーム（AppStore, GooglePlay等）
     * @param ReceiptDataDto $receiptData レシート情報
     * @param string $uniqueRequestId 一意なリクエストID（重複防止用）
     * @return VerificationResult 検証結果
     * @throws DuplicatePurchaseException 重複購入の場合
     * @throws Exception その他のエラー
     */
    public function processPurchase(
        string $billingPlatform,
        ReceiptData $receiptData,
        string $uniqueRequestId
    ): VerificationResult {
        // 1. 冪等性チェック（重複購入防止）
        if ($this->idempotencyService->isDuplicate($uniqueRequestId)) {
            Log::warning('Duplicate purchase request detected', [
                'unique_request_id' => $uniqueRequestId,
                'player_id' => $receiptData->playerId,
                'billing_platform' => $billingPlatform,
            ]);

            throw new DuplicatePurchaseException(
                "Purchase already processed: {$uniqueRequestId}"
            );
        }

        // 2. プラットフォーム選択（Factory）
        $platform = $this->platformFactory->create($billingPlatform);

        try {
            // 3. レシート検証（各プラットフォームの実装）
            $result = $platform->verifyReceipt($receiptData);

            // 4. 冪等性キー登録
            $this->idempotencyService->register($uniqueRequestId, $result);

            Log::info('Receipt verification successful', [
                'unique_request_id' => $uniqueRequestId,
                'transaction_id' => $result->transactionId,
                'product_id' => $result->productId,
                'billing_platform' => $billingPlatform,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Receipt verification failed', [
                'unique_request_id' => $uniqueRequestId,
                'player_id' => $receiptData->playerId,
                'billing_platform' => $billingPlatform,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * レシート検証のみ（冪等性チェックなし）
     * 
     * 冪等性管理が不要な場合や、別の方法で重複チェックを行う場合に使用
     * 
     * @param string $billingPlatform 決済プラットフォーム
     * @param ReceiptDataDto $receiptData レシート情報
     * @return VerificationResult 検証結果
     */
    public function verifyReceipt(
        string $billingPlatform,
        ReceiptData $receiptData
    ): VerificationResult {
        $platform = $this->platformFactory->create($billingPlatform);
        return $platform->verifyReceipt($receiptData);
    }

    /**
     * サブスクリプション状態確認
     * 
     * @param string $billingPlatform 決済プラットフォーム
     * @param string $subscriptionId サブスクリプションID
     * @return SubscriptionStatus サブスクリプション状態
     */
    public function checkSubscription(
        string $billingPlatform,
        string $subscriptionId
    ): SubscriptionStatus {
        $platform = $this->platformFactory->create($billingPlatform);
        
        return $platform->getSubscriptionStatus($subscriptionId);
    }

    /**
     * 返金確認
     * 
     * @param string $billingPlatform 決済プラットフォーム
     * @param string $transactionId トランザクションID
     * @return bool 返金されているか
     */
    public function isRefunded(
        string $billingPlatform,
        string $transactionId
    ): bool {
        $platform = $this->platformFactory->create($billingPlatform);
        
        return $platform->isRefunded($transactionId);
    }

    /**
     * プラットフォームがサポートされているかチェック
     * 
     * @param string $billingPlatform
     * @return bool
     */
    public function isSupportedPlatform(string $billingPlatform): bool
    {
        return $this->platformFactory->isSupported($billingPlatform);
    }
}
