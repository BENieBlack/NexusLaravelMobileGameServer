<?php

namespace Tests\Feature\Domain\InAppPurchase;

use App\Domain\InAppPurchase\Services\InAppPurchaseValidationService;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Mst\MstInAppPurchase;
use App\Models\Trx\TrxInAppPurchase;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 購入回数制限のテスト
 *
 * 判定そのものは _BasePurchaseLimitValidator が持っていて別途テストがある。
 * ここはアプリ側の繋ぎ込み — マスターの purchase_limit と
 * 履歴の purchase_count / purchase_count_reset_at を正しく渡せているか。
 *
 * 誤ると「上限まで買えない」か「上限を超えて買える」のどちらかになる。
 */
class PurchaseLimitTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const PRODUCT_ID = 990101;

    private int $sysPlayerId = 1;

    private InAppPurchaseValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);
        ClockUtility::setNow('2026-03-15 12:00:00');

        $this->cleanUp();
        $this->service = app(InAppPurchaseValidationService::class);
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ClockUtility::reset();
        ApiSession::clearForTest();

        parent::tearDown();
    }

    // ========================================
    // 制限をかけていない場合
    // ========================================

    #[Test]
    public function 制限が無ければ何回買っても通る(): void
    {
        $product = $this->makeProduct(purchaseLimit: null);

        $this->service->validatePurchaseLimit($product, $this->makeHistory(purchaseCount: 999), 'GooglePlay');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function 初回購入は履歴が無いので通る(): void
    {
        $product = $this->makeProduct(purchaseLimit: 1);

        $this->service->validatePurchaseLimit($product, null, 'GooglePlay');

        $this->addToAssertionCount(1);
    }

    // ========================================
    // リセットなし（買い切り）
    // ========================================

    #[Test]
    public function 上限に達していなければ通る(): void
    {
        $product = $this->makeProduct(purchaseLimit: 3);

        $this->service->validatePurchaseLimit($product, $this->makeHistory(purchaseCount: 2), 'GooglePlay');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function 上限に達したら弾かれる(): void
    {
        $product = $this->makeProduct(purchaseLimit: 3);

        try {
            $this->service->validatePurchaseLimit($product, $this->makeHistory(purchaseCount: 3), 'GooglePlay');
            $this->fail('上限に達しているのに通ってしまった');
        } catch (GameException $e) {
            $this->assertSame(GameErrorCode::PURCHASE_LIMIT_EXCEEDED, $e->getErrorCode());
            $this->assertStringContainsString('Limit: 3', $e->getMessage());
            $this->assertStringContainsString('Current: 3', $e->getMessage());
        }
    }

    #[Test]
    public function リセットなしは日付が変わっても持ち越す(): void
    {
        $product = $this->makeProduct(purchaseLimit: 1, purchaseLimitReset: 'None');
        $history = $this->makeHistory(purchaseCount: 1, resetAt: '2026-01-01 00:00:00');

        $this->expectException(GameException::class);

        $this->service->validatePurchaseLimit($product, $history, 'GooglePlay');
    }

    // ========================================
    // 日次・週次・月次のリセット
    // ========================================

    #[Test]
    public function 日次は日付が変われば買い直せる(): void
    {
        $product = $this->makeProduct(purchaseLimit: 1, purchaseLimitReset: 'Daily');

        // 前日に上限まで買っていても、日付が変わればリセットされる
        $this->service->validatePurchaseLimit(
            $product,
            $this->makeHistory(purchaseCount: 1, resetAt: '2026-03-14 23:59:59'),
            'GooglePlay'
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function 日次は同じ日なら弾かれる(): void
    {
        $product = $this->makeProduct(purchaseLimit: 1, purchaseLimitReset: 'Daily');

        $this->expectException(GameException::class);

        $this->service->validatePurchaseLimit(
            $product,
            $this->makeHistory(purchaseCount: 1, resetAt: '2026-03-15 00:00:00'),
            'GooglePlay'
        );
    }

    #[Test]
    public function 週次は週が変われば買い直せる(): void
    {
        $product = $this->makeProduct(purchaseLimit: 1, purchaseLimitReset: 'Weekly');

        // 2026-03-15 は日曜。前の週の日時ならリセットされる
        $this->service->validatePurchaseLimit(
            $product,
            $this->makeHistory(purchaseCount: 1, resetAt: '2026-03-05 12:00:00'),
            'GooglePlay'
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function 週次は同じ週なら弾かれる(): void
    {
        $product = $this->makeProduct(purchaseLimit: 1, purchaseLimitReset: 'Weekly');

        $this->expectException(GameException::class);

        $this->service->validatePurchaseLimit(
            $product,
            $this->makeHistory(purchaseCount: 1, resetAt: '2026-03-13 12:00:00'),
            'GooglePlay'
        );
    }

    #[Test]
    public function 月次は月が変われば買い直せる(): void
    {
        $product = $this->makeProduct(purchaseLimit: 1, purchaseLimitReset: 'Monthly');

        $this->service->validatePurchaseLimit(
            $product,
            $this->makeHistory(purchaseCount: 1, resetAt: '2026-02-28 23:59:59'),
            'GooglePlay'
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function 月次は同じ月なら弾かれる(): void
    {
        $product = $this->makeProduct(purchaseLimit: 1, purchaseLimitReset: 'Monthly');

        $this->expectException(GameException::class);

        $this->service->validatePurchaseLimit(
            $product,
            $this->makeHistory(purchaseCount: 1, resetAt: '2026-03-01 00:00:00'),
            'GooglePlay'
        );
    }

    #[Test]
    public function リセットされた回数は0として案内される(): void
    {
        // リセット済みなら上限に達していないので、そもそも例外にならない
        $product = $this->makeProduct(purchaseLimit: 2, purchaseLimitReset: 'Daily');

        $this->service->validatePurchaseLimit(
            $product,
            $this->makeHistory(purchaseCount: 5, resetAt: '2026-03-14 12:00:00'),
            'GooglePlay'
        );

        $this->addToAssertionCount(1);
    }

    // ========================================
    // 新しいリセット日時
    // ========================================

    #[Test]
    public function リセットが要るときだけ新しい日時を返す(): void
    {
        $this->assertSame(
            '2026-03-15 12:00:00',
            $this->service->getNewResetDateIfNeeded('Daily', '2026-03-14 12:00:00')
        );

        $this->assertNull($this->service->getNewResetDateIfNeeded('Daily', '2026-03-15 00:00:00'));
        $this->assertNull($this->service->getNewResetDateIfNeeded('None', '2026-01-01 00:00:00'));
    }

    private function makeProduct(?int $purchaseLimit, string $purchaseLimitReset = 'None'): MstInAppPurchase
    {
        DB::connection('mst')->table('mst_in_app_purchase')->insert([
            'id' => self::PRODUCT_ID,
            'type' => 'Diamond',
            'paid_diamond_amount' => 100,
            'vip_point' => 0,
            'purchase_limit' => $purchaseLimit,
            'purchase_limit_reset' => $purchaseLimitReset,
            'is_active' => true,
        ]);

        $this->refreshMstCache();

        return MstInAppPurchase::query()->where('id', self::PRODUCT_ID)->firstOrFail();
    }

    private function makeHistory(int $purchaseCount, ?string $resetAt = null): TrxInAppPurchase
    {
        // trx_in_app_purchase は複合主キーで id を持たない
        DB::connection('trx1')->table('trx_in_app_purchase')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'billing_platform' => 'GooglePlay',
            'mst_in_app_purchase_id' => self::PRODUCT_ID,
            'transaction_id' => 'txn-limit-'.$purchaseCount,
            'total_purchase_count' => $purchaseCount,
            'purchase_count' => $purchaseCount,
            'purchase_count_reset_at' => $resetAt,
            'is_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return TrxInAppPurchase::query()
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('billing_platform', 'GooglePlay')
            ->where('mst_in_app_purchase_id', self::PRODUCT_ID)
            ->firstOrFail();
    }

    private function cleanUp(): void
    {
        DB::connection('trx1')->table('trx_in_app_purchase')->delete();
        DB::connection('mst')->table('mst_in_app_purchase')->where('id', self::PRODUCT_ID)->delete();
        $this->refreshMstCache();
    }
}
