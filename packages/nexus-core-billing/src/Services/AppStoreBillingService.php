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
use Illuminate\Support\Facades\Log;

/**
 * App Store 決済サービス
 * 
 * Apple App Store のレシート検証とサブスクリプション管理を担当
 */
class AppStoreBillingService implements BillingPlatformInterface
{
    /**
     * App Storeのサブスクリプション状態（Get All Subscription Statuses）
     *
     * @see https://developer.apple.com/documentation/appstoreserverapi
     */
    private const STATUS_ACTIVE = 1;

    private const STATUS_EXPIRED = 2;

    private const STATUS_BILLING_RETRY = 3;

    private const STATUS_GRACE_PERIOD = 4;

    private const STATUS_REVOKED = 5;

    /**
     * 返金履歴を辿るページ数の上限（無限ループ防止）
     */
    private const MAX_REFUND_PAGES = 20;

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

        // 2. Apple API 呼び出し（接続先はAPP_STORE_ENVIRONMENTに従う）
        $response = $this->apiClient->verifyReceipt($payload);

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
     * App Store Server API の Get All Subscription Statuses を使う。
     * $subscriptionId には購読の transactionId（originalTransactionId）を渡す。
     */
    public function fetchSubscriptionStatus(string $subscriptionId, ?string $purchaseToken = null): Subscription
    {
        $response = $this->apiClient->fetchSubscriptionStatuses($subscriptionId);

        $lastTransaction = $this->findLastTransaction($response);

        if ($lastTransaction === null) {
            throw new PlatformApiException(
                "No subscription status found for transaction: {$subscriptionId}"
            );
        }

        $status = (int) ($lastTransaction['status'] ?? self::STATUS_EXPIRED);

        $transactionInfo = isset($lastTransaction['signedTransactionInfo'])
            ? $this->apiClient->decodeSignedPayload((string) $lastTransaction['signedTransactionInfo'])
            : [];

        $renewalInfo = isset($lastTransaction['signedRenewalInfo'])
            ? $this->apiClient->decodeSignedPayload((string) $lastTransaction['signedRenewalInfo'])
            : [];

        $expiresDateMs = $transactionInfo['expiresDate'] ?? null;

        return new Subscription(
            isActive: in_array($status, [self::STATUS_ACTIVE, self::STATUS_GRACE_PERIOD], true),
            expiresAt: $expiresDateMs !== null
                ? CarbonImmutable::createFromTimestampMs((int) $expiresDateMs)->format('Y-m-d H:i:s')
                : CarbonImmutable::now()->format('Y-m-d H:i:s'),
            autoRenew: (int) ($renewalInfo['autoRenewStatus'] ?? 0) === 1,
            state: $this->mapSubscriptionState($status),
        );
    }

    /**
     * {@inheritDoc}
     *
     * App Store Server API の Get Refund History を使う。
     * 返金された取引だけが返るため、対象のtransactionIdが含まれていれば返金済み。
     *
     * hasMore が続く限り revision を渡して辿る（上限20ページ）。
     */
    public function isRefunded(string $transactionId): bool
    {
        $revision = null;

        // 返金が多いプレイヤーではページ分割されるため、hasMoreを辿る
        for ($page = 1; $page <= self::MAX_REFUND_PAGES; $page++) {
            $response = $this->apiClient->fetchRefundHistory($transactionId, $revision);

            foreach ($response['signedTransactions'] ?? [] as $signedTransaction) {
                $transaction = $this->apiClient->decodeSignedPayload((string) $signedTransaction);

                if (($transaction['transactionId'] ?? null) === $transactionId
                    || ($transaction['originalTransactionId'] ?? null) === $transactionId) {
                    return true;
                }
            }

            if (($response['hasMore'] ?? false) !== true) {
                return false;
            }

            $revision = isset($response['revision']) ? (string) $response['revision'] : null;

            if ($revision === null || $revision === '') {
                // hasMoreがtrueなのにrevisionが無い場合は辿れない
                Log::warning('App Store refund history has more pages but no revision', [
                    'transaction_id' => $transactionId,
                ]);

                return false;
            }
        }

        // 上限まで辿っても見つからない場合は「返金なし」とはみなさず、判断を呼び出し側に委ねる
        throw new PlatformApiException(
            "App Store refund history exceeded {$this->maxRefundPages()} pages for transaction: {$transactionId}"
        );
    }

    /**
     * 返金履歴を辿るページ数の上限
     */
    private function maxRefundPages(): int
    {
        return self::MAX_REFUND_PAGES;
    }

    /**
     * サブスクリプション状態のレスポンスから直近の取引を取り出す
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    private function findLastTransaction(array $response): ?array
    {
        foreach ($response['data'] ?? [] as $group) {
            foreach ($group['lastTransactions'] ?? [] as $transaction) {
                return $transaction;
            }
        }

        return null;
    }

    /**
     * Appleの状態コードをアプリ内の状態に変換する
     */
    private function mapSubscriptionState(int $status): string
    {
        return match ($status) {
            self::STATUS_ACTIVE => BillingConst::SUBSCRIPTION_STATE_ACTIVE,
            self::STATUS_GRACE_PERIOD, self::STATUS_BILLING_RETRY => BillingConst::SUBSCRIPTION_STATE_GRACE_PERIOD,
            self::STATUS_REVOKED => BillingConst::SUBSCRIPTION_STATE_CANCELLED,
            default => BillingConst::SUBSCRIPTION_STATE_EXPIRED,
        };
    }
}
