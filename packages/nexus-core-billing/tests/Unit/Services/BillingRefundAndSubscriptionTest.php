<?php

namespace NexusBilling\Tests\Unit\Services;

use NexusBilling\ApiClients\AppStoreApiClient;
use NexusBilling\ApiClients\GooglePlayApiClient;
use NexusBilling\Constants\BillingConst;
use NexusBilling\Exceptions\InvalidReceiptException;
use NexusBilling\Exceptions\PlatformApiException;
use NexusBilling\Services\AppStoreBillingService;
use NexusBilling\Services\GooglePlayBillingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 返金確認とサブスクリプション状態のテスト
 */
class BillingRefundAndSubscriptionTest extends TestCase
{
    private const PACKAGE_NAME = 'com.example.nexus';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_play.package_name' => self::PACKAGE_NAME]);
    }

    #[Test]
    public function 返金済みの注文はtrueを返す(): void
    {
        $service = new GooglePlayBillingService($this->googlePlayClientReturning(['state' => 'refunded']));

        $this->assertTrue($service->isRefunded('GPA.0000-1111-2222-33333'));
    }

    #[Test]
    public function 有効な注文はfalseを返す(): void
    {
        $service = new GooglePlayBillingService($this->googlePlayClientReturning(['state' => 'processed']));

        $this->assertFalse($service->isRefunded('GPA.0000-1111-2222-33333'));
    }

    #[Test]
    public function 購入トークンなしでサブスクリプションは照会できない(): void
    {
        $service = new GooglePlayBillingService($this->createMock(GooglePlayApiClient::class));

        $this->expectException(InvalidReceiptException::class);
        $this->expectExceptionMessage('Purchase token is required');

        $service->fetchSubscriptionStatus('subscription_monthly');
    }

    #[Test]
    public function app_storeの返金履歴に含まれていれば返金済み(): void
    {
        $serverApiClient = $this->createMock(AppStoreApiClient::class);
        $serverApiClient->method('fetchRefundHistory')
            ->willReturn(['signedTransactions' => ['signed-jws']]);
        $serverApiClient->method('decodeSignedPayload')
            ->willReturn(['transactionId' => '1000000000000001']);

        $service = new AppStoreBillingService($serverApiClient);

        $this->assertTrue($service->isRefunded('1000000000000001'));
    }

    #[Test]
    public function app_storeの返金履歴を最後のページまで辿る(): void
    {
        $serverApiClient = $this->createMock(AppStoreApiClient::class);
        $serverApiClient->method('fetchRefundHistory')->willReturnCallback(
            fn (string $transactionId, ?string $revision = null) => $revision === null
                ? ['signedTransactions' => ['page-1'], 'hasMore' => true, 'revision' => 'rev-2']
                : ['signedTransactions' => ['page-2'], 'hasMore' => false]
        );
        $serverApiClient->method('decodeSignedPayload')->willReturnCallback(
            fn (string $signed) => $signed === 'page-2'
                ? ['transactionId' => '1000000000000001']   // 2ページ目に該当の返金がある
                : ['transactionId' => '9999999999999999']
        );

        $service = new AppStoreBillingService($serverApiClient);

        $this->assertTrue($service->isRefunded('1000000000000001'));
    }

    #[Test]
    public function app_storeの返金履歴が空なら返金されていない(): void
    {
        $serverApiClient = $this->createMock(AppStoreApiClient::class);
        $serverApiClient->method('fetchRefundHistory')->willReturn(['signedTransactions' => []]);

        $service = new AppStoreBillingService($serverApiClient);

        $this->assertFalse($service->isRefunded('1000000000000001'));
    }

    #[Test]
    public function app_storeの有効なサブスクリプションを取得できる(): void
    {
        $serverApiClient = $this->createMock(AppStoreApiClient::class);
        $serverApiClient->method('fetchSubscriptionStatuses')->willReturn([
            'data' => [[
                'lastTransactions' => [[
                    'status' => 1, // Active
                    'signedTransactionInfo' => 'signed-transaction',
                    'signedRenewalInfo' => 'signed-renewal',
                ]],
            ]],
        ]);
        $serverApiClient->method('decodeSignedPayload')->willReturnCallback(
            fn (string $signed) => $signed === 'signed-transaction'
                ? ['expiresDate' => 1758326400000]
                : ['autoRenewStatus' => 1]
        );

        $service = new AppStoreBillingService($serverApiClient);

        $subscription = $service->fetchSubscriptionStatus('1000000000000001');

        $this->assertTrue($subscription->isActive());
        $this->assertTrue($subscription->isAutoRenew());
        $this->assertSame(BillingConst::SUBSCRIPTION_STATE_ACTIVE, $subscription->getState());
    }

    #[Test]
    public function app_storeの失効したサブスクリプションは無効として返す(): void
    {
        $serverApiClient = $this->createMock(AppStoreApiClient::class);
        $serverApiClient->method('fetchSubscriptionStatuses')->willReturn([
            'data' => [[
                'lastTransactions' => [[
                    'status' => 2, // Expired
                    'signedTransactionInfo' => 'signed-transaction',
                ]],
            ]],
        ]);
        $serverApiClient->method('decodeSignedPayload')->willReturn(['expiresDate' => 1755648000000]);

        $service = new AppStoreBillingService($serverApiClient);

        $subscription = $service->fetchSubscriptionStatus('1000000000000001');

        $this->assertFalse($subscription->isActive());
        $this->assertFalse($subscription->isAutoRenew());
        $this->assertSame(BillingConst::SUBSCRIPTION_STATE_EXPIRED, $subscription->getState());
    }

    #[Test]
    public function app_storeで購読が見つからなければ例外になる(): void
    {
        $serverApiClient = $this->createMock(AppStoreApiClient::class);
        $serverApiClient->method('fetchSubscriptionStatuses')->willReturn(['data' => []]);

        $service = new AppStoreBillingService($serverApiClient);

        // 「期限切れ」を返すと未購読として扱われてしまうため、失敗させる
        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('No subscription status found');

        $service->fetchSubscriptionStatus('1000000000000001');
    }

    /**
     * fetchOrder が指定の応答を返すクライアントのモックを作る
     *
     * @param  array<string, mixed>  $order
     */
    private function googlePlayClientReturning(array $order): GooglePlayApiClient
    {
        $client = $this->createMock(GooglePlayApiClient::class);
        $client->method('fetchOrder')->willReturn($order);

        return $client;
    }
}
