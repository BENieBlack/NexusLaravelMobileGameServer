<?php

namespace Tests\Feature\Domain\Wallet\Services;

use App\Persistence\ApiSession;
use LaravelWallet\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LaravelWallet\Exceptions\InsufficientBalanceException;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * WalletServiceのテスト
 *
 * 無償/有償通貨の管理、FIFO消費、有効期限管理をテスト
 */
class WalletServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId = 1;

    private WalletService $walletService;

    private QueryManager $queryManager;

    /**
     * Override to prevent automatic transaction wrapping
     * because we need to manually control transactions with QueryManager
     */
    public function beginDatabaseTransaction(): void
    {
        // Do nothing - let tests manage their own transactions
    }

    protected function setUp(): void
    {
        parent::setUp();
        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->walletService = app(WalletService::class);
        $this->queryManager = app(QueryManager::class);
    }

    protected function tearDown(): void
    {
        // Clear all test data
        DB::connection('trx1')->table('trx_wallet')->truncate();
        DB::connection('trx1')->table('trx_wallet_balance')->truncate();

        ApiSession::clearForTest();
        $this->queryManager->clear();
        parent::tearDown();
    }

    #[Test]
    public function 無償通貨を加算できる(): void
    {
        // Execute
        $result = $this->walletService->addCurrency(
            $this->sysPlayerId,
            'gold',
            freeAmount: 1000,
            paidAmount: 0
        );

        // Save to DB
        $this->queryManager->execAllQuery();

        // Verify
        $this->assertSame(1000, $result->getFreeAmount());
        $this->assertSame(0, $result->getPaidAmount());
        $this->assertSame(1000, $result->getTotalAmount());

        // Verify DB
        $wallet = DB::connection('trx1')
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->first();

        $this->assertNotNull($wallet);
        $this->assertSame(1000, $wallet->free_amount);
        $this->assertSame(0, $wallet->paid_amount);

        // Verify balance record
        $balance = DB::connection('trx1')
            ->table('trx_wallet_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->where('is_paid', false)
            ->first();

        $this->assertNotNull($balance);
        $this->assertSame(1000, $balance->current_amount);
        $this->assertSame(1000, $balance->initial_amount);
    }

    #[Test]
    public function 有償通貨を加算できる(): void
    {
        // Execute
        $result = $this->walletService->addCurrency(
            $this->sysPlayerId,
            'gold',
            freeAmount: 0,
            paidAmount: 500
        );

        // Save to DB
        $this->queryManager->execAllQuery();

        // Verify
        $this->assertSame(0, $result->getFreeAmount());
        $this->assertSame(500, $result->getPaidAmount());
        $this->assertSame(500, $result->getTotalAmount());

        // Verify DB
        $wallet = DB::connection('trx1')
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->first();

        $this->assertNotNull($wallet);
        $this->assertSame(0, $wallet->free_amount);
        $this->assertSame(500, $wallet->paid_amount);

        // Verify balance record
        $balance = DB::connection('trx1')
            ->table('trx_wallet_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->where('is_paid', true)
            ->first();

        $this->assertNotNull($balance);
        $this->assertSame(500, $balance->current_amount);
    }

    #[Test]
    public function 無償と有償通貨を同時に加算できる(): void
    {
        // Execute
        $result = $this->walletService->addCurrency(
            $this->sysPlayerId,
            'gold',
            freeAmount: 1000,
            paidAmount: 500
        );

        // Save to DB
        $this->queryManager->execAllQuery();

        // Verify
        $this->assertSame(1000, $result->getFreeAmount());
        $this->assertSame(500, $result->getPaidAmount());
        $this->assertSame(1500, $result->getTotalAmount());

        // Verify DB
        $wallet = DB::connection('trx1')
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->first();

        $this->assertNotNull($wallet);
        $this->assertSame(1000, $wallet->free_amount);
        $this->assertSame(500, $wallet->paid_amount);

        // Verify balance records (2 records: free + paid)
        $balances = DB::connection('trx1')
            ->table('trx_wallet_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->orderBy('is_paid', 'DESC')
            ->get();

        $this->assertCount(2, $balances);
        $this->assertTrue((bool) $balances[0]->is_paid);
        $this->assertSame(500, $balances[0]->current_amount);
        $this->assertFalse((bool) $balances[1]->is_paid);
        $this->assertSame(1000, $balances[1]->current_amount);
    }

    #[Test]
    public function 有償通貨を優先的に消費する(): void
    {
        // Prepare: Add 1000 free + 500 paid
        $this->walletService->addCurrency(
            $this->sysPlayerId,
            'gold',
            freeAmount: 1000,
            paidAmount: 500
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume 300 (should consume from paid first)
        $result = $this->walletService->consumeCurrency(
            $this->sysPlayerId,
            'gold',
            300
        );
        $this->queryManager->execAllQuery();

        // Verify result
        $this->assertSame(0, $result->getFreeAmount());
        $this->assertSame(300, $result->getPaidAmount());
        $this->assertSame(300, $result->getTotalAmount());
        $this->assertSame(1200, $result->getCurrentBalance()); // 1500 - 300 = 1200

        // Verify DB - paid should be 200, free should be 1000
        $wallet = DB::connection('trx1')
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->first();

        $this->assertSame(1000, $wallet->free_amount); // unchanged
        $this->assertSame(200, $wallet->paid_amount);  // 500 - 300
    }

    #[Test]
    public function 有償通貨を使い切った後に無償通貨を消費する(): void
    {
        // Prepare: Add 1000 free + 500 paid
        $this->walletService->addCurrency(
            $this->sysPlayerId,
            'gold',
            freeAmount: 1000,
            paidAmount: 500
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume 800 (500 from paid + 300 from free)
        $result = $this->walletService->consumeCurrency(
            $this->sysPlayerId,
            'gold',
            800
        );
        $this->queryManager->execAllQuery();

        // Verify result
        $this->assertSame(300, $result->getFreeAmount());
        $this->assertSame(500, $result->getPaidAmount());
        $this->assertSame(800, $result->getTotalAmount());
        $this->assertSame(700, $result->getCurrentBalance()); // 1500 - 800 = 700

        // Verify DB - paid should be 0, free should be 700
        $wallet = DB::connection('trx1')
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->first();

        $this->assertSame(700, $wallet->free_amount);  // 1000 - 300
        $this->assertSame(0, $wallet->paid_amount);    // 500 - 500 = 0
    }

    #[Test]
    public function 複数回加算すると合計される(): void
    {
        // First add
        $this->walletService->addCurrency(
            $this->sysPlayerId,
            'gold',
            freeAmount: 500,
            paidAmount: 200
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Second add
        $result = $this->walletService->addCurrency(
            $this->sysPlayerId,
            'gold',
            freeAmount: 300,
            paidAmount: 100
        );
        $this->queryManager->execAllQuery();

        // Verify
        $this->assertSame(300, $result->getFreeAmount());
        $this->assertSame(100, $result->getPaidAmount());
        $this->assertSame(400, $result->getTotalAmount());
        $this->assertSame(1100, $result->getCurrentBalance()); // (500+200) + (300+100)

        // Verify DB
        $wallet = DB::connection('trx1')
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->first();

        $this->assertSame(800, $wallet->free_amount);  // 500 + 300
        $this->assertSame(300, $wallet->paid_amount);  // 200 + 100
    }

    #[Test]
    public function 残高不足の場合は例外が発生する(): void
    {
        // Prepare: Add 100 gold
        $this->walletService->addCurrency(
            $this->sysPlayerId,
            'gold',
            freeAmount: 100,
            paidAmount: 0
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute & Verify
        $this->expectException(InsufficientBalanceException::class);

        $this->walletService->consumeCurrency(
            $this->sysPlayerId,
            'gold',
            200
        );
    }

    #[Test]
    public function 存在しない通貨を消費すると例外が発生する(): void
    {
        $this->expectException(InsufficientBalanceException::class);

        $this->walletService->consumeCurrency(
            $this->sysPlayerId,
            'gold',
            100
        );
    }

    #[Test]
    public function get_balanceで残高を取得できる(): void
    {
        // Prepare
        $this->walletService->addCurrency(
            $this->sysPlayerId,
            'gold',
            freeAmount: 1000,
            paidAmount: 500
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute
        $balance = $this->walletService->getBalance($this->sysPlayerId, 'gold');

        // Verify
        $this->assertSame(1000, $balance->getFreeAmount());
        $this->assertSame(500, $balance->getPaidAmount());
        $this->assertSame(1500, $balance->getTotalAmount());
    }

    #[Test]
    public function 存在しない通貨のget_balanceは0を返す(): void
    {
        $balance = $this->walletService->getBalance($this->sysPlayerId, 'nonexistent');
        $this->assertSame(0, $balance->getFreeAmount());
        $this->assertSame(0, $balance->getPaidAmount());
        $this->assertSame(0, $balance->getTotalAmount());
    }

    #[Test]
    public function 有効期限付きで通貨を加算できる(): void
    {
        $expireAt = CarbonImmutable::now()->addDays(7);

        // Execute
        $this->walletService->addCurrency(
            $this->sysPlayerId,
            'event_coin',
            freeAmount: 500,
            paidAmount: 0,
            expireAt: $expireAt
        );
        $this->queryManager->execAllQuery();

        // Verify DB
        $balance = DB::connection('trx1')
            ->table('trx_wallet_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'event_coin')
            ->first();

        $this->assertNotNull($balance);
        $this->assertSame(500, $balance->current_amount);
        $this->assertNotNull($balance->expire_at);
        $this->assertSame($expireAt->toDateTimeString(), $balance->expire_at);
    }

    #[Test]
    public function 有効期限切れの通貨を削除できる(): void
    {
        // Prepare: Add expired and non-expired currency
        $expiredDate = CarbonImmutable::now()->subDays(1);
        $futureDate = CarbonImmutable::now()->addDays(7);

        // Add expired free currency
        DB::connection('trx1')->table('trx_wallet')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'mst_item_id' => 'event_coin',
            'free_amount' => 300,
            'paid_amount' => 200,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('trx1')->table('trx_wallet_balance')->insert([
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => 'event_coin',
                'current_amount' => 300,
                'initial_amount' => 300,
                'expire_at' => $expiredDate,
                'is_paid' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => 'event_coin',
                'current_amount' => 200,
                'initial_amount' => 200,
                'expire_at' => $futureDate,
                'is_paid' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute
        $expired = $this->walletService->removeExpiredCurrency(
            $this->sysPlayerId,
            'event_coin'
        );
        $this->queryManager->execAllQuery();

        // Verify
        $this->assertSame(300, $expired);

        // Verify DB - wallet should have 200 left (500 - 300)
        $wallet = DB::connection('trx1')
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'event_coin')
            ->first();

        $this->assertSame(0, $wallet->free_amount);    // 300 - 300 (expired)
        $this->assertSame(200, $wallet->paid_amount);  // unchanged

        // Verify balance - expired should be 0, future should be 200
        $balances = DB::connection('trx1')
            ->table('trx_wallet_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'event_coin')
            ->orderBy('id')
            ->get();

        $this->assertSame(0, $balances[0]->current_amount);   // expired
        $this->assertSame(200, $balances[1]->current_amount); // future
    }

    #[Test]
    public function fif_o順で有効期限が近いものから消費される(): void
    {
        // Prepare: Add 2 paid currency with different expiration dates
        $nearExpire = CarbonImmutable::now()->addDays(1);
        $farExpire = CarbonImmutable::now()->addDays(30);

        DB::connection('trx1')->table('trx_wallet')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'mst_item_id' => 'event_coin',
            'free_amount' => 0,
            'paid_amount' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert in reverse order to test FIFO ordering
        DB::connection('trx1')->table('trx_wallet_balance')->insert([
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => 'event_coin',
                'current_amount' => 300,
                'initial_amount' => 300,
                'expire_at' => $farExpire,  // Inserted first but expires later
                'is_paid' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => 'event_coin',
                'current_amount' => 200,
                'initial_amount' => 200,
                'expire_at' => $nearExpire,  // Inserted later but expires sooner
                'is_paid' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume 150 (should consume from near-expire first)
        $this->walletService->consumeCurrency(
            $this->sysPlayerId,
            'event_coin',
            150
        );
        $this->queryManager->execAllQuery();

        // Verify - near-expire should be 50 (200-150), far-expire should be 300
        $balances = DB::connection('trx1')
            ->table('trx_wallet_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'event_coin')
            ->orderBy('expire_at')
            ->get();

        $this->assertSame(50, $balances[0]->current_amount);  // near-expire: 200 - 150
        $this->assertSame(300, $balances[1]->current_amount); // far-expire: unchanged
    }

    #[Test]
    public function 有償通貨のみを消費できる(): void
    {
        // Prepare: Add 1000 free + 500 paid
        $this->walletService->addCurrency(
            $this->sysPlayerId,
            'gold',
            freeAmount: 1000,
            paidAmount: 500
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume all paid (500)
        $this->walletService->consumeCurrency(
            $this->sysPlayerId,
            'gold',
            500
        );
        $this->queryManager->execAllQuery();

        // Verify - free unchanged, paid becomes 0
        $wallet = DB::connection('trx1')
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->first();

        $this->assertSame(1000, $wallet->free_amount); // unchanged
        $this->assertSame(0, $wallet->paid_amount);    // 500 - 500 = 0
    }

    #[Test]
    public function 大量の通貨を正確に消費できる(): void
    {
        // Prepare: Add 1000000 free + 500000 paid
        $this->walletService->addCurrency(
            $this->sysPlayerId,
            'gold',
            freeAmount: 1000000,
            paidAmount: 500000
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume 700000 (500000 from paid + 200000 from free)
        $result = $this->walletService->consumeCurrency(
            $this->sysPlayerId,
            'gold',
            700000
        );
        $this->queryManager->execAllQuery();

        // Verify
        $this->assertSame(700000, $result->getTotalAmount());
        $this->assertSame(800000, $result->getCurrentBalance()); // 1500000 - 700000

        $wallet = DB::connection('trx1')
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->first();

        $this->assertSame(800000, $wallet->free_amount);  // 1000000 - 200000
        $this->assertSame(0, $wallet->paid_amount);       // 500000 - 500000 = 0
    }

    #[Test]
    public function 複数種類の通貨を独立して管理できる(): void
    {
        // Add gold
        $this->walletService->addCurrency(
            $this->sysPlayerId,
            'gold',
            freeAmount: 1000,
            paidAmount: 500
        );

        // Add event_coin
        $this->walletService->addCurrency(
            $this->sysPlayerId,
            'event_coin',
            freeAmount: 2000,
            paidAmount: 800
        );

        $this->queryManager->execAllQuery();

        // Verify gold
        $gold = DB::connection('trx1')
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->first();

        $this->assertSame(1000, $gold->free_amount);
        $this->assertSame(500, $gold->paid_amount);

        // Verify event_coin
        $eventCoin = DB::connection('trx1')
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'event_coin')
            ->first();

        $this->assertSame(2000, $eventCoin->free_amount);
        $this->assertSame(800, $eventCoin->paid_amount);
    }

    #[Test]
    public function 有償と無償を混在させた複数のbalanceレコードを正しく消費できる(): void
    {
        // Prepare: Add multiple balance records
        DB::connection('trx1')->table('trx_wallet')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'mst_item_id' => 'gold',
            'free_amount' => 600,
            'paid_amount' => 900,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('trx1')->table('trx_wallet_balance')->insert([
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => 'gold',
                'current_amount' => 300,
                'initial_amount' => 300,
                'expire_at' => null,
                'is_paid' => true,  // Paid #1
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => 'gold',
                'current_amount' => 400,
                'initial_amount' => 400,
                'expire_at' => null,
                'is_paid' => false,  // Free #1
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => 'gold',
                'current_amount' => 600,
                'initial_amount' => 600,
                'expire_at' => null,
                'is_paid' => true,  // Paid #2
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sys_player_id' => $this->sysPlayerId,
                'mst_item_id' => 'gold',
                'current_amount' => 200,
                'initial_amount' => 200,
                'expire_at' => null,
                'is_paid' => false,  // Free #2
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume 1000 (should consume paid first: 300 + 600 = 900, then free: 100)
        $this->walletService->consumeCurrency(
            $this->sysPlayerId,
            'gold',
            1000
        );
        $this->queryManager->execAllQuery();

        // Verify wallet totals
        $wallet = DB::connection('trx1')
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->first();

        $this->assertSame(500, $wallet->free_amount);  // 600 - 100
        $this->assertSame(0, $wallet->paid_amount);    // 900 - 900 = 0

        // Verify balance records (ordered by is_paid DESC, id ASC)
        $balances = DB::connection('trx1')
            ->table('trx_wallet_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'gold')
            ->orderBy('is_paid', 'DESC')
            ->orderBy('id', 'ASC')
            ->get();

        // Paid #1: consumed completely
        $this->assertTrue((bool) $balances[0]->is_paid);
        $this->assertSame(0, $balances[0]->current_amount);

        // Paid #2: consumed completely
        $this->assertTrue((bool) $balances[1]->is_paid);
        $this->assertSame(0, $balances[1]->current_amount);

        // Free #1: consumed 100
        $this->assertFalse((bool) $balances[2]->is_paid);
        $this->assertSame(300, $balances[2]->current_amount);  // 400 - 100

        // Free #2: not consumed
        $this->assertFalse((bool) $balances[3]->is_paid);
        $this->assertSame(200, $balances[3]->current_amount);
    }
}
