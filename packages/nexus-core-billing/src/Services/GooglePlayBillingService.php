<?php

namespace NexusBilling\Services;

use Carbon\CarbonImmutable;
use NexusBilling\ApiClients\GooglePlayApiClient;
use NexusBilling\Constants\BillingConst;
use NexusBilling\Contracts\BillingPlatformInterface;
use NexusBilling\DataTransferObjects\Receipt;
use NexusBilling\DataTransferObjects\Verification;
use NexusBilling\Exceptions\DuplicatePurchaseException;
use NexusBilling\Exceptions\InvalidReceiptException;
use NexusBilling\ValueObjects\Subscription;

/**
 * Google Play 決済サービス
 *
 * Google Play のレシート検証とサブスクリプション管理を担当
 */
class GooglePlayBillingService implements BillingPlatformInterface
{
    /**
     * 返金済みとみなす注文状態（Orders API の state）
     *
     * @var list<string>
     */
    private const REFUNDED_ORDER_STATES = ['refunded', 'partially_refunded', 'canceled'];

    public function __construct(
        private readonly GooglePlayApiClient $apiClient,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function verifyReceipt(Receipt $receipt): Verification
    {
        if (empty($receipt->getPurchaseToken()) || empty($receipt->getProductId())) {
            throw new InvalidReceiptException(
                'Purchase token and product ID are required for Google Play'
            );
        }

        // 1. パッケージ名を取得
        $packageName = config('services.google_play.package_name');

        // 2. Google Play Developer API 呼び出し
        $response = $this->apiClient->verifyPurchase(
            packageName: $packageName,
            productId: $receipt->getProductId(),
            token: $receipt->getPurchaseToken()
        );

        // 3. 購入状態確認
        if (! isset($response['purchaseState']) || $response['purchaseState'] !== 0) {
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
        //
        // Note: productId はリクエスト由来。Google Playの購入検証レスポンスには
        // 商品IDが含まれないため、_BaseBuyUseCase::validateProductId() は
        // GooglePlayでは常に一致する。実際の防御は、購入トークンを
        // 商品IDつきのURL（purchases/products/{productId}/tokens/{token}）で
        // 問い合わせている点にある。商品が違えばGoogle側がエラーを返す。
        return new Verification(
            isValid: true,
            transactionId: $response['orderId'],
            productId: $receipt->getProductId(),
            purchaseDate: CarbonImmutable::createFromTimestampMs((int) $response['purchaseTimeMillis'])->format('Y-m-d H:i:s'),
            quantity: (int) ($response['quantity'] ?? 1),
            originalTransactionId: $response['orderId'],
            rawResponse: $response,
            priceAmountMicros: isset($response['priceAmountMicros']) ? (int) $response['priceAmountMicros'] : null,
            priceCurrencyCode: $response['priceCurrencyCode'] ?? null,
        );
    }

    /**
     * {@inheritDoc}
     *
     * Google Playのサブスクリプション照会は購入トークンが必須。
     *
     * Note: サブスク商品の購入フローがまだ無いため、現時点で呼び出し元は無い。
     * 販売開始時に必要な作業はREADMEの「サブスクリプション対応の残作業」を参照。
     */
    public function fetchSubscriptionStatus(string $subscriptionId, ?string $purchaseToken = null): Subscription
    {
        if (empty($purchaseToken)) {
            throw new InvalidReceiptException(
                'Purchase token is required to check a Google Play subscription'
            );
        }

        $packageName = config('services.google_play.package_name');

        $response = $this->apiClient->fetchSubscription(
            packageName: $packageName,
            subscriptionId: $subscriptionId,
            token: $purchaseToken
        );

        $isActive = isset($response['paymentState']) && $response['paymentState'] === 1;
        $autoRenew = $response['autoRenewing'] ?? false;

        return new Subscription(
            isActive: $isActive,
            expiresAt: CarbonImmutable::createFromTimestampMs((int) $response['expiryTimeMillis'])->format('Y-m-d H:i:s'),
            autoRenew: $autoRenew,
            state: $isActive ? BillingConst::SUBSCRIPTION_STATE_ACTIVE : BillingConst::SUBSCRIPTION_STATE_EXPIRED,
        );
    }

    /**
     * {@inheritDoc}
     *
     * Orders APIで注文状態を引き、返金・チャージバック済みかを判定する。
     * $transactionId は verifyReceipt が返す orderId。
     */
    public function isRefunded(string $transactionId): bool
    {
        $packageName = config('services.google_play.package_name');

        $order = $this->apiClient->fetchOrder($packageName, $transactionId);

        $state = $order['state'] ?? null;

        return in_array($state, self::REFUNDED_ORDER_STATES, true);
    }
}
