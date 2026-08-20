<?php

namespace NexusBilling\Services;

use NexusBilling\ApiClients\AppStoreApiClient;
use NexusBilling\Constants\BillingConst;
use NexusBilling\Contracts\BillingPlatformInterface;
use NexusBilling\DataTransferObjects\Receipt;
use NexusBilling\ValueObjects\Subscription;
use NexusBilling\DataTransferObjects\Verification;
use NexusBilling\Exceptions\InvalidReceiptException;
use NexusBilling\Exceptions\PlatformApiException;
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
    public function verifyReceipt(Receipt $receipt): Verification
    {
        if (empty($receipt->getReceipt())) {
            throw new InvalidReceiptException('Receipt data is required for App Store');
        }

        // 1. App Store API に送信するペイロード作成
        $payload = [
            'receipt-data' => $receipt->getReceipt(),
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
        return new Verification(
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
     *
     * 未実装。レシート検証に使っている /verifyReceipt はレシート本体が必要で、
     * transactionIdだけでは引けない。App Store Server API
     * （ES256のJWT認証 + JWS署名付きレスポンスの検証）の実装が必要。
     *
     * 「期限切れ」を返すと未購読として扱われてしまうため、値を返さず失敗させる。
     */
    public function fetchSubscriptionStatus(string $subscriptionId, ?string $purchaseToken = null): Subscription
    {
        throw new PlatformApiException(
            'Checking an App Store subscription requires the App Store Server API, which is not implemented yet'
        );
    }

    /**
     * {@inheritDoc}
     *
     * 未実装。falseを返すと「返金されていない」と誤って扱われ、
     * 返金済みの購入に対して付与を続けてしまうため、値を返さず失敗させる。
     */
    public function isRefunded(string $transactionId): bool
    {
        throw new PlatformApiException(
            'Refund lookup requires the App Store Server API, which is not implemented yet'
        );
    }
}
