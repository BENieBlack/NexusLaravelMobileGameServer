<?php

namespace NexusBilling\Tests\Unit\Services;

use NexusBilling\ApiClients\AppStoreApiClient;
use NexusBilling\ApiClients\GooglePlayApiClient;
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
    public function AppStoreの返金確認は未実装として失敗する(): void
    {
        $service = new AppStoreBillingService($this->createMock(AppStoreApiClient::class));

        // falseを返すと「返金されていない」と誤って扱われるため、例外にしている
        $this->expectException(PlatformApiException::class);
        $this->expectExceptionMessage('App Store Server API');

        $service->isRefunded('1000000000000000');
    }

    #[Test]
    public function AppStoreのサブスクリプション照会は未実装として失敗する(): void
    {
        $service = new AppStoreBillingService($this->createMock(AppStoreApiClient::class));

        $this->expectException(PlatformApiException::class);

        $service->fetchSubscriptionStatus('1000000000000000');
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
