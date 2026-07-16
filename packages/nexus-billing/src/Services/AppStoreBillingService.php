<?php

namespace LaravelMobileBilling\Services;

use LaravelMobileBilling\ApiClients\AppStoreApiClient;
use LaravelMobileBilling\Constants\BillingConst;
use LaravelMobileBilling\Contracts\BillingPlatformInterface;
use LaravelMobileBilling\DTOs\ReceiptData;
use LaravelMobileBilling\DTOs\SubscriptionStatus;
use LaravelMobileBilling\DTOs\VerificationResult;
use LaravelMobileBilling\Exceptions\InvalidReceiptException;
use Carbon\CarbonImmutable;

/**
 * App Store 決済サービス
 * 
 * Apple App Store のレシート検証とサブスクリプション管理を担当
 */
class AppStoreBillingService implements BillingPlatformInterface
{
    public function __construct(
        private readonly AppStoreApiClient $apiClient,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function verifyReceipt(ReceiptData $receiptData): VerificationDto
    {
        if (empty($receiptData->receipt)) {
            throw new InvalidReceiptException('Receipt data is required for App Store');
        }

        // 1. App Store API に送信するペイロード作成
        $payload = [
            'receipt-data' => $receiptData->receipt,
            'password' => config('services.app_store.shared_secret'),
            'exclude-old-transactions' => true,
        ];

        // 2. Apple API 呼び出し
        $isSandbox = config('app.env') !== 'production';
        $response = $this->apiClient->verifyReceipt($payload, $isSandbox);

        // 3. レスポンス検証
        if (!isset($response['status']) || $response['status'] !== 0) {
            $status = $response['status'] ?? 'unknown';
            throw new InvalidReceiptException(
                "App Store receipt verification failed with status: {$status}"
            );
        }

        // 4. トランザクション情報抽出
        $latestReceipt = $response['receipt']['in_app'][0] ?? null;
        if (!$latestReceipt) {
            throw new InvalidReceiptException('No transaction found in receipt');
        }

        // 5. 検証結果を返す
        return new VerificationResult(
            isValid: true,
            transactionId: $latestReceipt['transaction_id'],
            productId: $latestReceipt['product_id'],
            purchaseDate: CarbonImmutable::createFromTimestampMs((int)$latestReceipt['purchase_date_ms'])->format('Y-m-d H:i:s'),
            quantity: (int)($latestReceipt['quantity'] ?? 1),
            originalTransactionId: $latestReceipt['original_transaction_id'],
            rawResponse: $response,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptionStatus(string $subscriptionId): SubscriptionDto
    {
        // TODO: App Store Server API を使用した実装
        // 現在は未実装
        
        return new SubscriptionStatus(
            isActive: false,
            expiresAt: CarbonImmutable::now()->format('Y-m-d H:i:s'),
            autoRenew: false,
            state: BillingConst::SUBSCRIPTION_STATE_EXPIRED,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isRefunded(string $transactionId): bool
    {
        // TODO: レシート再検証またはApp Store Server APIで返金フラグをチェック
        // 現在は常にfalseを返す
        
        return false;
    }
}
