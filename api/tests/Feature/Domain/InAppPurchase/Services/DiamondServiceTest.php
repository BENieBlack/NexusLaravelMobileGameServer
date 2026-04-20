<?php

namespace Tests\Feature\Domain\InAppPurchase\Services;

use App\Domain\InAppPurchase\Services\DiamondService;
use App\Models\Trx\TrxDiamond;
use App\Persistence\ApiSession;
use App\Persistence\QueryManager;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * DiamondServiceのテスト
 * 
 * 無償/有償ダイヤモンドの管理、消費順序をテスト
 * DiamondServiceは無償→有償の順で消費する仕様
 */
class DiamondServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId = 1;
    private string $platform = 'Apple';
    private DiamondService $diamondService;
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
        
        $this->diamondService = app(DiamondService::class);
        $this->queryManager = app(QueryManager::class);
    }

    protected function tearDown(): void
    {
        // Clear all test data
        DB::connection('trx1')->table('trx_diamond')->truncate();
        DB::connection('trx1')->table('trx_diamond_balance')->truncate();
        
        ApiSession::clearForTest();
        $this->queryManager->clear();
        parent::tearDown();
    }

    #[Test]
    public function 無償ダイヤモンドを加算できる(): void
    {
        // Execute
        $this->diamondService->addDiamond(
            $this->sysPlayerId,
            $this->platform,
            amount: 1000,
            isPaid: false
        );

        // Save to DB
        $this->queryManager->execAllQuery();

        // Verify DB
        $diamond = DB::connection('trx1')
            ->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('platform', $this->platform)
            ->first();

        $this->assertNotNull($diamond);
        $this->assertSame(1000, $diamond->free_amount);
        $this->assertSame(0, $diamond->paid_amount);
    }

    #[Test]
    public function 有償ダイヤモンドを加算できる(): void
    {
        // Execute
        $this->diamondService->addDiamond(
            $this->sysPlayerId,
            $this->platform,
            amount: 500,
            isPaid: true
        );

        // Save to DB
        $this->queryManager->execAllQuery();

        // Verify DB
        $diamond = DB::connection('trx1')
            ->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('platform', $this->platform)
            ->first();

        $this->assertNotNull($diamond);
        $this->assertSame(0, $diamond->free_amount);
        $this->assertSame(500, $diamond->paid_amount);
    }

    #[Test]
    public function 無償ダイヤモンドを優先的に消費する(): void
    {
        // Prepare: Manually insert diamond with both free and paid
        DB::connection('trx1')->table('trx_diamond')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'platform' => $this->platform,
            'free_amount' => 1000,
            'paid_amount' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume 300 (should consume from free first)
        $this->diamondService->consumeDiamond($this->sysPlayerId, 300, isPaidOnly: false);
        $this->queryManager->execAllQuery();

        // Verify DB - free should be 700, paid should be 500 (unchanged)
        $diamond = DB::connection('trx1')
            ->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('platform', $this->platform)
            ->first();

        $this->assertSame(700, $diamond->free_amount);  // 1000 - 300
        $this->assertSame(500, $diamond->paid_amount);  // unchanged
    }

    #[Test]
    public function 無償ダイヤモンドを使い切った後に有償ダイヤモンドを消費する(): void
    {
        // Prepare: Manually insert diamond with both free and paid
        DB::connection('trx1')->table('trx_diamond')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'platform' => $this->platform,
            'free_amount' => 1000,
            'paid_amount' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume 1200 (1000 from free + 200 from paid)
        $this->diamondService->consumeDiamond($this->sysPlayerId, 1200, isPaidOnly: false);
        $this->queryManager->execAllQuery();

        // Verify DB - free should be 0, paid should be 300
        $diamond = DB::connection('trx1')
            ->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('platform', $this->platform)
            ->first();

        $this->assertSame(0, $diamond->free_amount);    // 1000 - 1000 = 0
        $this->assertSame(300, $diamond->paid_amount);  // 500 - 200
    }

    #[Test]
    public function 有償ダイヤモンドのみを消費できる(): void
    {
        // Prepare: Manually insert diamond with both free and paid
        DB::connection('trx1')->table('trx_diamond')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'platform' => $this->platform,
            'free_amount' => 1000,
            'paid_amount' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume 200 paid only
        $this->diamondService->consumeDiamond($this->sysPlayerId, 200, isPaidOnly: true);
        $this->queryManager->execAllQuery();

        // Verify DB - free unchanged, paid reduced
        $diamond = DB::connection('trx1')
            ->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('platform', $this->platform)
            ->first();

        $this->assertSame(1000, $diamond->free_amount); // unchanged
        $this->assertSame(300, $diamond->paid_amount);  // 500 - 200
    }

    #[Test]
    public function 複数回加算すると合計される(): void
    {
        // First add
        $this->diamondService->addDiamond($this->sysPlayerId, $this->platform, 500, isPaid: false);
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Second add (free)
        $this->diamondService->addDiamond($this->sysPlayerId, $this->platform, 300, isPaid: false);
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Third add (paid)
        $this->diamondService->addDiamond($this->sysPlayerId, $this->platform, 200, isPaid: true);
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Fourth add (paid)
        $this->diamondService->addDiamond($this->sysPlayerId, $this->platform, 100, isPaid: true);
        $this->queryManager->execAllQuery();

        // Verify DB
        $diamond = DB::connection('trx1')
            ->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('platform', $this->platform)
            ->first();

        $this->assertSame(800, $diamond->free_amount);  // 500 + 300
        $this->assertSame(300, $diamond->paid_amount);  // 200 + 100
    }

    #[Test]
    public function 残高不足の場合は例外が発生する(): void
    {
        // Prepare: Add 100 diamonds
        $this->diamondService->addDiamond($this->sysPlayerId, $this->platform, 100, isPaid: false);
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute & Verify
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ダイヤモンド残高が不足しています');

        $this->diamondService->consumeDiamond($this->sysPlayerId, 200, isPaidOnly: false);
    }

    #[Test]
    public function 有償ダイヤモンド残高不足の場合は例外が発生する(): void
    {
        // Prepare: Manually insert diamond with 1000 free + 100 paid
        DB::connection('trx1')->table('trx_diamond')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'platform' => $this->platform,
            'free_amount' => 1000,
            'paid_amount' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute & Verify - trying to consume 200 paid but only have 100
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('有償ダイヤモンド残高が不足しています');

        $this->diamondService->consumeDiamond($this->sysPlayerId, 200, isPaidOnly: true);
    }

    #[Test]
    public function 存在しないダイヤモンドを消費すると例外が発生する(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ダイヤモンド残高が不足しています');

        $this->diamondService->consumeDiamond($this->sysPlayerId, 100, isPaidOnly: false);
    }

    #[Test]
    public function 異なるプラットフォームで独立して管理される(): void
    {
        // Add to Apple platform
        $this->diamondService->addDiamond($this->sysPlayerId, 'Apple', 1000, isPaid: false);
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->diamondService->addDiamond($this->sysPlayerId, 'Apple', 500, isPaid: true);
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);
        
        // Add to Google platform
        $this->diamondService->addDiamond($this->sysPlayerId, 'Google', 2000, isPaid: false);
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->diamondService->addDiamond($this->sysPlayerId, 'Google', 800, isPaid: true);
        $this->queryManager->execAllQuery();

        // Verify Apple
        $appleDiamond = DB::connection('trx1')
            ->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('platform', 'Apple')
            ->first();

        $this->assertSame(1000, $appleDiamond->free_amount);
        $this->assertSame(500, $appleDiamond->paid_amount);

        // Verify Google
        $googleDiamond = DB::connection('trx1')
            ->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('platform', 'Google')
            ->first();

        $this->assertSame(2000, $googleDiamond->free_amount);
        $this->assertSame(800, $googleDiamond->paid_amount);
    }

    #[Test]
    public function 大量のダイヤモンドを正確に消費できる(): void
    {
        // Prepare: Manually insert diamond with 100000 free + 50000 paid
        DB::connection('trx1')->table('trx_diamond')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'platform' => $this->platform,
            'free_amount' => 100000,
            'paid_amount' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume 120000 (100000 from free + 20000 from paid)
        $this->diamondService->consumeDiamond($this->sysPlayerId, 120000, isPaidOnly: false);
        $this->queryManager->execAllQuery();

        // Verify
        $diamond = DB::connection('trx1')
            ->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('platform', $this->platform)
            ->first();

        $this->assertSame(0, $diamond->free_amount);      // 100000 - 100000 = 0
        $this->assertSame(30000, $diamond->paid_amount);  // 50000 - 20000
    }
}
