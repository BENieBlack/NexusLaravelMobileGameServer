<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Mockery;
use NexusBilling\Exceptions\PlatformApiException;
use NexusBilling\Facades\BillingFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * billing:detect-refunds のテスト
 *
 * プラットフォームへの問い合わせはモックし、
 * 走査対象の絞り込みとログ記録を検証する。
 */
class DetectRefundsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    #[Test]
    public function 返金された購入を_refundedとして記録する(): void
    {
        $this->insertPurchaseLog('req-1', 'GPA.1111', 'GooglePlay');
        $this->insertPurchaseLog('req-2', 'GPA.2222', 'GooglePlay');

        $this->mockBillingFacade(refundedTransactionIds: ['GPA.1111']);

        $this->artisan('billing:detect-refunds')->assertSuccessful();

        $refunded = DB::connection('log1')->table('log_in_app_purchase')
            ->where('status', 'Refunded')
            ->get();

        $this->assertCount(1, $refunded);
        $this->assertSame('GPA.1111', $refunded[0]->receipt_id);
        $this->assertSame('req-1:refunded', $refunded[0]->unique_request_id);

        // 購入時のログは残したまま
        $this->assertSame(
            2,
            DB::connection('log1')->table('log_in_app_purchase')->where('status', 'Purchased')->count()
        );
    }

    #[Test]
    public function dry_runでは記録しない(): void
    {
        $this->insertPurchaseLog('req-1', 'GPA.1111', 'GooglePlay');

        $this->mockBillingFacade(refundedTransactionIds: ['GPA.1111']);

        $this->artisan('billing:detect-refunds --dry-run')
            ->expectsOutputToContain('返金を検知')
            ->assertSuccessful();

        $this->assertSame(
            0,
            DB::connection('log1')->table('log_in_app_purchase')->where('status', 'Refunded')->count()
        );
    }

    #[Test]
    public function 記録済みの返金は再確認しない(): void
    {
        $this->insertPurchaseLog('req-1', 'GPA.1111', 'GooglePlay');
        $this->insertPurchaseLog('req-1:refunded', 'GPA.1111', 'GooglePlay', status: 'Refunded');

        // 問い合わせ自体が行われないこと
        $facade = Mockery::mock(BillingFacade::class);
        $facade->shouldNotReceive('isRefunded');
        $this->app->instance(BillingFacade::class, $facade);

        $this->artisan('billing:detect-refunds')->assertSuccessful();
    }

    #[Test]
    public function 問い合わせに失敗しても他の購入の確認を続ける(): void
    {
        $this->insertPurchaseLog('req-1', '1000000000000001', 'AppStore');
        $this->insertPurchaseLog('req-2', 'GPA.2222', 'GooglePlay');

        $facade = Mockery::mock(BillingFacade::class);
        $facade->shouldReceive('isRefunded')
            ->with('AppStore', '1000000000000001')
            ->andThrow(new PlatformApiException('App Store Server API is not configured'));
        $facade->shouldReceive('isRefunded')
            ->with('GooglePlay', 'GPA.2222')
            ->andReturnTrue();
        $this->app->instance(BillingFacade::class, $facade);

        // 失敗が1件あるため終了コードは失敗
        $this->artisan('billing:detect-refunds')->assertFailed();

        // 確認できた分は記録される
        $refunded = DB::connection('log1')->table('log_in_app_purchase')->where('status', 'Refunded')->get();
        $this->assertCount(1, $refunded);
        $this->assertSame('GPA.2222', $refunded[0]->receipt_id);
    }

    #[Test]
    public function 期間外の購入は確認しない(): void
    {
        $this->insertPurchaseLog('req-old', 'GPA.OLD', 'GooglePlay', systemAt: now()->subDays(60)->format('Y-m-d H:i:s'));

        $facade = Mockery::mock(BillingFacade::class);
        $facade->shouldNotReceive('isRefunded');
        $this->app->instance(BillingFacade::class, $facade);

        $this->artisan('billing:detect-refunds --days=30')->assertSuccessful();
    }

    /**
     * @param  list<string>  $refundedTransactionIds
     */
    private function mockBillingFacade(array $refundedTransactionIds): void
    {
        $facade = Mockery::mock(BillingFacade::class);
        $facade->shouldReceive('isRefunded')
            ->andReturnUsing(fn (string $platform, string $transactionId) => in_array($transactionId, $refundedTransactionIds, true));

        $this->app->instance(BillingFacade::class, $facade);
    }

    private function insertPurchaseLog(
        string $uniqueRequestId,
        string $receiptId,
        string $billingPlatform,
        string $status = 'Purchased',
        ?string $systemAt = null,
    ): void {
        DB::connection('log1')->table('log_in_app_purchase')->insert([
            'unique_request_id' => $uniqueRequestId,
            'sys_player_id' => 1,
            'platform' => $billingPlatform === 'AppStore' ? 'apple' : 'google',
            'billing_platform' => $billingPlatform,
            'receipt_id' => $receiptId,
            'receipt' => json_encode([]),
            'status' => $status,
            'mst_in_app_purchase_id' => '1',
            'currency_code' => 'JPY',
            'pay_amount' => 490.00,
            'pay_string' => '¥490',
            'system_at' => $systemAt ?? now()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function cleanUp(): void
    {
        foreach (['log1', 'log2', 'log3'] as $connection) {
            DB::connection($connection)->table('log_in_app_purchase')->delete();
        }
    }
}
