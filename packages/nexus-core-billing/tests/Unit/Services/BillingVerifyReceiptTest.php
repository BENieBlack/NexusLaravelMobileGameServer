<?php

namespace NexusBilling\Tests\Unit\Services;

use NexusBilling\ApiClients\AppStoreApiClient;
use NexusBilling\ApiClients\GooglePlayApiClient;
use NexusBilling\Constants\BillingConst;
use NexusBilling\DataTransferObjects\Receipt;
use NexusBilling\Exceptions\DuplicatePurchaseException;
use NexusBilling\Exceptions\InvalidReceiptException;
use NexusBilling\Exceptions\PlatformApiException;
use NexusBilling\Services\AppStoreBillingService;
use NexusBilling\Services\GooglePlayBillingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * レシート検証のテスト
 *
 * ストアの応答をそのまま信じるのではなく、購入状態・消費済み・
 * トランザクションの有無を見て弾く層。ここを通ると課金が確定するため、
 * 通す条件と弾く条件の両方を押さえる。
 *
 * APIクライアントはモックにする。通信そのものは
 * AppStoreServerApiTest / GooglePlayApiClientTest が見ている。
 */
class BillingVerifyReceiptTest extends TestCase
{
    private const PACKAGE_NAME = 'com.example.nexus';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_play.package_name' => self::PACKAGE_NAME,
            'services.app_store.shared_secret' => 'shared-secret',
        ]);
    }

    // ========================================
    // Google Play
    // ========================================

    #[Test]
    public function google_playのレシートを検証できる(): void
    {
        $service = new GooglePlayBillingService($this->googlePlayClient([
            'purchaseState' => 0,
            'consumptionState' => 0,
            'orderId' => 'GPA.0000-1111-2222-33333',
            'purchaseTimeMillis' => '1773576000000',
            'quantity' => 2,
            'priceAmountMicros' => '980000000',
            'priceCurrencyCode' => 'JPY',
        ]));

        $verification = $service->verifyReceipt($this->googlePlayReceipt());

        $this->assertTrue($verification->getIsValid());
        $this->assertSame('GPA.0000-1111-2222-33333', $verification->getTransactionId());
        $this->assertSame('GPA.0000-1111-2222-33333', $verification->getOriginalTransactionId());
        $this->assertSame('diamond_100', $verification->getProductId(), '商品IDはリクエスト由来');
        $this->assertSame('2026-03-15 12:00:00', $verification->getPurchaseDate());
        $this->assertSame(2, $verification->getQuantity());
        $this->assertSame(980000000, $verification->getPriceAmountMicros());
        $this->assertSame('JPY', $verification->getPriceCurrencyCode());
    }

    #[Test]
    public function google_playの数量と価格は省略できる(): void
    {
        $service = new GooglePlayBillingService($this->googlePlayClient([
            'purchaseState' => 0,
            'orderId' => 'GPA.0000-1111-2222-33333',
            'purchaseTimeMillis' => '1773576000000',
        ]));

        $verification = $service->verifyReceipt($this->googlePlayReceipt());

        $this->assertSame(1, $verification->getQuantity(), '既定は1');
        $this->assertNull($verification->getPriceAmountMicros());
        $this->assertNull($verification->getPriceCurrencyCode());
    }

    #[Test]
    public function 購入トークンが無ければ検証できない(): void
    {
        $service = new GooglePlayBillingService($this->createMock(GooglePlayApiClient::class));

        $this->expectException(InvalidReceiptException::class);
        $this->expectExceptionMessage('Purchase token and product ID are required');

        $service->verifyReceipt(new Receipt(
            playerId: 1,
            billingPlatform: 'google_play',
            purchaseToken: null,
            productId: 'diamond_100',
        ));
    }

    #[Test]
    public function 商品idが無ければ検証できない(): void
    {
        $service = new GooglePlayBillingService($this->createMock(GooglePlayApiClient::class));

        $this->expectException(InvalidReceiptException::class);
        $this->expectExceptionMessage('Purchase token and product ID are required');

        $service->verifyReceipt(new Receipt(
            playerId: 1,
            billingPlatform: 'google_play',
            purchaseToken: 'purchase-token',
            productId: null,
        ));
    }

    #[Test]
    public function 購入完了していない注文は弾く(): void
    {
        // purchaseState 1 はキャンセル済み、2 は保留中
        $service = new GooglePlayBillingService($this->googlePlayClient(['purchaseState' => 1]));

        $this->expectException(InvalidReceiptException::class);
        $this->expectExceptionMessage('Google Play purchase not in purchased state: 1');

        $service->verifyReceipt($this->googlePlayReceipt());
    }

    #[Test]
    public function 購入状態が入っていない応答は弾く(): void
    {
        $service = new GooglePlayBillingService($this->googlePlayClient(['orderId' => 'GPA.X']));

        $this->expectException(InvalidReceiptException::class);
        $this->expectExceptionMessage('not in purchased state: unknown');

        $service->verifyReceipt($this->googlePlayReceipt());
    }

    #[Test]
    public function 消費済みの購入は二重付与を防ぐため弾く(): void
    {
        $service = new GooglePlayBillingService($this->googlePlayClient([
            'purchaseState' => 0,
            'consumptionState' => 1,
            'orderId' => 'GPA.X',
            'purchaseTimeMillis' => '1773576000000',
        ]));

        $this->expectException(DuplicatePurchaseException::class);
        $this->expectExceptionMessage('Purchase already consumed');

        $service->verifyReceipt($this->googlePlayReceipt());
    }

    #[Test]
    public function google_playのサブスクリプション状態を取れる(): void
    {
        $client = $this->createMock(GooglePlayApiClient::class);
        $client->method('fetchSubscription')->willReturn([
            'paymentState' => 1,
            'autoRenewing' => true,
            'expiryTimeMillis' => '1773576000000',
        ]);

        $subscription = (new GooglePlayBillingService($client))
            ->fetchSubscriptionStatus('subscription_monthly', 'purchase-token');

        $this->assertTrue($subscription->isActive());
        $this->assertTrue($subscription->isAutoRenew());
        $this->assertSame('2026-03-15 12:00:00', $subscription->getExpiresAt());
        $this->assertSame(BillingConst::SUBSCRIPTION_STATE_ACTIVE, $subscription->getState());
    }

    #[Test]
    public function 支払いが確定していないサブスクリプションは無効(): void
    {
        // paymentState 0 は支払い保留
        $client = $this->createMock(GooglePlayApiClient::class);
        $client->method('fetchSubscription')->willReturn([
            'paymentState' => 0,
            'autoRenewing' => false,
            'expiryTimeMillis' => '1773576000000',
        ]);

        $subscription = (new GooglePlayBillingService($client))
            ->fetchSubscriptionStatus('subscription_monthly', 'purchase-token');

        $this->assertFalse($subscription->isActive());
        $this->assertFalse($subscription->isAutoRenew());
        $this->assertSame(BillingConst::SUBSCRIPTION_STATE_EXPIRED, $subscription->getState());
    }

    // ========================================
    // App Store
    // ========================================

    #[Test]
    public function app_storeのレシートを検証できる(): void
    {
        $service = new AppStoreBillingService($this->appStoreClient([
            'status' => 0,
            'receipt' => [
                'in_app' => [[
                    'transaction_id' => '1000000000000001',
                    'original_transaction_id' => '1000000000000000',
                    'product_id' => 'diamond_100',
                    'purchase_date_ms' => '1773576000000',
                    'quantity' => '3',
                ]],
            ],
        ]));

        $verification = $service->verifyReceipt($this->appStoreReceipt());

        $this->assertTrue($verification->getIsValid());
        $this->assertSame('1000000000000001', $verification->getTransactionId());
        $this->assertSame('1000000000000000', $verification->getOriginalTransactionId());
        $this->assertSame('diamond_100', $verification->getProductId(), '商品IDはレシート由来');
        $this->assertSame('2026-03-15 12:00:00', $verification->getPurchaseDate());
        $this->assertSame(3, $verification->getQuantity());
        $this->assertNull($verification->getPriceAmountMicros(), 'verifyReceiptは価格を返さない');
    }

    #[Test]
    public function app_storeの数量は省略できる(): void
    {
        $service = new AppStoreBillingService($this->appStoreClient([
            'status' => 0,
            'receipt' => [
                'in_app' => [[
                    'transaction_id' => '1000000000000001',
                    'original_transaction_id' => '1000000000000000',
                    'product_id' => 'diamond_100',
                    'purchase_date_ms' => '1773576000000',
                ]],
            ],
        ]));

        $this->assertSame(1, $service->verifyReceipt($this->appStoreReceipt())->getQuantity());
    }

    #[Test]
    public function レシートデータが無ければ検証できない(): void
    {
        $service = new AppStoreBillingService($this->createMock(AppStoreApiClient::class));

        $this->expectException(InvalidReceiptException::class);
        $this->expectExceptionMessage('Receipt data is required for App Store');

        $service->verifyReceipt(new Receipt(
            playerId: 1,
            billingPlatform: 'app_store',
            receipt: null,
        ));
    }

    #[Test]
    public function appleが不正と判定したレシートは弾く(): void
    {
        // 21002 はレシートが壊れている
        $service = new AppStoreBillingService($this->appStoreClient(['status' => 21002]));

        $this->expectException(InvalidReceiptException::class);
        $this->expectExceptionMessage('App Store receipt verification failed with status: 21002');

        $service->verifyReceipt($this->appStoreReceipt());
    }

    #[Test]
    public function 状態が入っていない応答は弾く(): void
    {
        $service = new AppStoreBillingService($this->appStoreClient([]));

        $this->expectException(InvalidReceiptException::class);
        $this->expectExceptionMessage('failed with status: unknown');

        $service->verifyReceipt($this->appStoreReceipt());
    }

    #[Test]
    public function 取引が入っていないレシートは弾く(): void
    {
        $service = new AppStoreBillingService($this->appStoreClient([
            'status' => 0,
            'receipt' => ['in_app' => []],
        ]));

        $this->expectException(InvalidReceiptException::class);
        $this->expectExceptionMessage('No transaction found in receipt');

        $service->verifyReceipt($this->appStoreReceipt());
    }

    #[Test]
    public function appleの状態コードをアプリ内の状態へ写す(): void
    {
        // 1=有効, 2=失効, 3=請求リトライ, 4=猶予期間, 5=取り消し
        $expected = [
            1 => [true, BillingConst::SUBSCRIPTION_STATE_ACTIVE],
            2 => [false, BillingConst::SUBSCRIPTION_STATE_EXPIRED],
            3 => [false, BillingConst::SUBSCRIPTION_STATE_GRACE_PERIOD],
            4 => [true, BillingConst::SUBSCRIPTION_STATE_GRACE_PERIOD],
            5 => [false, BillingConst::SUBSCRIPTION_STATE_CANCELLED],
            99 => [false, BillingConst::SUBSCRIPTION_STATE_EXPIRED],
        ];

        foreach ($expected as $status => [$isActive, $state]) {
            $subscription = $this->appStoreSubscriptionService($status)
                ->fetchSubscriptionStatus('1000000000000001');

            $this->assertSame($isActive, $subscription->isActive(), "status {$status} の有効判定");
            $this->assertSame($state, $subscription->getState(), "status {$status} の状態");
        }
    }

    #[Test]
    public function 状態コードが無ければ失効として扱う(): void
    {
        $client = $this->createMock(AppStoreApiClient::class);
        $client->method('fetchSubscriptionStatuses')->willReturn([
            'data' => [['lastTransactions' => [[]]]],
        ]);

        $subscription = (new AppStoreBillingService($client))->fetchSubscriptionStatus('1000000000000001');

        $this->assertFalse($subscription->isActive());
        $this->assertSame(BillingConst::SUBSCRIPTION_STATE_EXPIRED, $subscription->getState());
    }

    #[Test]
    public function 返金履歴が上限ページを超えたら判断を委ねる(): void
    {
        // 「返金なし」と決め打つと、返金済みの購入を通してしまう
        $client = $this->createMock(AppStoreApiClient::class);
        $client->method('fetchRefundHistory')->willReturn([
            'signedTransactions' => [],
            'hasMore' => true,
            'revision' => 'next-page',
        ]);

        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('exceeded 20 pages');

        (new AppStoreBillingService($client))->isRefunded('1000000000000001');
    }

    #[Test]
    public function 続きがあるのにrevisionが無ければ辿るのをやめる(): void
    {
        $client = $this->createMock(AppStoreApiClient::class);
        $client->method('fetchRefundHistory')->willReturn([
            'signedTransactions' => [],
            'hasMore' => true,
        ]);

        $this->assertFalse((new AppStoreBillingService($client))->isRefunded('1000000000000001'));
    }

    /**
     * 指定した状態コードを返すApp Storeのサービスを組む
     */
    private function appStoreSubscriptionService(int $status): AppStoreBillingService
    {
        $client = $this->createMock(AppStoreApiClient::class);
        $client->method('fetchSubscriptionStatuses')->willReturn([
            'data' => [[
                'lastTransactions' => [[
                    'status' => $status,
                    'signedTransactionInfo' => 'signed-transaction',
                    'signedRenewalInfo' => 'signed-renewal',
                ]],
            ]],
        ]);
        $client->method('decodeSignedPayload')->willReturn([
            'expiresDate' => 1773576000000,
            'autoRenewStatus' => 1,
        ]);

        return new AppStoreBillingService($client);
    }

    private function googlePlayReceipt(): Receipt
    {
        return new Receipt(
            playerId: 1,
            billingPlatform: 'google_play',
            purchaseToken: 'purchase-token',
            productId: 'diamond_100',
        );
    }

    private function appStoreReceipt(): Receipt
    {
        return new Receipt(
            playerId: 1,
            billingPlatform: 'app_store',
            receipt: 'base64-receipt-data',
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function googlePlayClient(array $response): GooglePlayApiClient
    {
        $client = $this->createMock(GooglePlayApiClient::class);
        $client->method('verifyPurchase')->willReturn($response);

        return $client;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function appStoreClient(array $response): AppStoreApiClient
    {
        $client = $this->createMock(AppStoreApiClient::class);
        $client->method('verifyReceipt')->willReturn($response);

        return $client;
    }
}
