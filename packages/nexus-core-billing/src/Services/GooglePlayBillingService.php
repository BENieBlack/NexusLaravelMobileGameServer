<?php

namespace NexusBilling\Services;

use NexusBilling\ApiClients\GooglePlayApiClient;
use NexusBilling\Constants\BillingConst;
use NexusBilling\Contracts\BillingPlatformInterface;
use NexusBilling\DTOs\ReceiptDto;
use NexusBilling\DTOs\SubscriptionStatus;
use NexusBilling\DTOs\VerificationDto;
use NexusBilling\Exceptions\DuplicatePurchaseException;
use NexusBilling\Exceptions\InvalidReceiptException;
use Carbon\CarbonImmutable;

/**
 * Google Play 決済サービス
 * 
 * Google Play のレシート検証とサブスクリプション管理を担当
 */
class GooglePlayBillingService implements BillingPlatformInterface
{
    public function __construct(
        private readonly GooglePlayApiClient $apiClient,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function verifyReceipt(ReceiptDto $receiptDto): VerificationDto
    {
        if (empty($receiptDto->getPurchaseToken()) || empty($receiptDto->getProductId())) {
            throw new InvalidReceiptException(
                'Purchase token and product ID are required for Google Play'
            );
        }

        // 1. パッケージ名を取得
        $packageName = config('services.google_play.package_name');

        // 2. Google Play Developer API 呼び出し
        $response = $this->apiClient->verifyPurchase(
            packageName: $packageName,
            productId: $receiptDto->getProductId(),
            token: $receiptDto->getPurchaseToken()
        );

        // 3. 購入状態確認
        if (!isset($response['purchaseState']) || $response['purchaseState'] !== 0) {
            $state = $response['purchaseState'] ?? 'unknown';
            throw new InvalidReceiptException(
                "Google Play purchase not in purchased state: {$state}"
            );
        }

        // 4. 消費済みチェック
        if (isset($response['consumptionState']) && $response['consumptionState'] === 1) {
            throw new DuplicatePurchaseException('Purchase already consumed');
        }

        // 5. 検証結果を返す
        return new VerificationDto(
            isValid: true,
            transactionId: $response['orderId'],
            productId: $receiptDto->getProductId(),
            purchaseDate: CarbonImmutable::createFromTimestampMs((int)$response['purchaseTimeMillis'])->format('Y-m-d H:i:s'),
            quantity: (int)($response['quantity'] ?? 1),
            originalTransactionId: $response['orderId'],
            rawResponse: $response,
            priceAmountMicros: isset($response['priceAmountMicros']) ? (int)$response['priceAmountMicros'] : null,
            priceCurrencyCode: $response['priceCurrencyCode'] ?? null,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptionStatus(string $subscriptionId): Subscription
    {
        // サブスクリプショントークンと商品IDが必要
        // 実際の実装では別途パラメータが必要
        
        $packageName = config('services.google_play.package_name');
        
        // TODO: 実際のトークンを渡す必要がある
        $response = $this->apiClient->getSubscription(
            packageName: $packageName,
            subscriptionId: $subscriptionId,
            token: '' // トークンが必要
        );

        $isActive = isset($response['paymentState']) && $response['paymentState'] === 1;
        $autoRenew = $response['autoRenewing'] ?? false;

        return new SubscriptionStatus(
            isActive: $isActive,
            expiresAt: CarbonImmutable::createFromTimestampMs((int)$response['expiryTimeMillis'])->format('Y-m-d H:i:s'),
            autoRenew: $autoRenew,
            state: $isActive ? BillingConst::SUBSCRIPTION_STATE_ACTIVE : BillingConst::SUBSCRIPTION_STATE_EXPIRED,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isRefunded(string $transactionId): bool
    {
        // TODO: Google Play では購入情報を再取得して返金状態をチェック
        // 現在は常にfalseを返す
        
        return false;
    }
}
