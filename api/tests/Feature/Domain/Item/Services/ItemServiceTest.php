<?php

namespace Tests\Feature\Domain\Item\Services;

use App\Domain\Item\Services\ItemService;
use App\Models\Trx\TrxItem;
use App\Persistence\ApiSession;
use App\Persistence\QueryManager;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * ItemServiceのテスト
 * 
 * 無償/有償アイテムの管理、有償優先消費をテスト
 */
class ItemServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId = 1;
    private ItemService $itemService;
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
        
        $this->itemService = app(ItemService::class);
        $this->queryManager = app(QueryManager::class);
    }

    protected function tearDown(): void
    {
        // Clear all test data
        DB::connection('trx1')->table('trx_item')->truncate();
        
        ApiSession::clearForTest();
        $this->queryManager->clear();
        parent::tearDown();
    }

    #[Test]
    public function 無償アイテムを加算できる(): void
    {
        // Execute
        $this->itemService->addItem(
            $this->sysPlayerId,
            'item_potion_001',
            freeAmount: 100,
            paidAmount: 0
        );

        // Save to DB
        $this->queryManager->execAllQuery();

        // Verify DB
        $item = DB::connection('trx1')
            ->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'item_potion_001')
            ->first();

        $this->assertNotNull($item);
        $this->assertSame(100, $item->free_amount);
        $this->assertSame(0, $item->paid_amount);
    }

    #[Test]
    public function 有償アイテムを加算できる(): void
    {
        // Execute
        $this->itemService->addItem(
            $this->sysPlayerId,
            'item_potion_001',
            freeAmount: 0,
            paidAmount: 50
        );

        // Save to DB
        $this->queryManager->execAllQuery();

        // Verify DB
        $item = DB::connection('trx1')
            ->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'item_potion_001')
            ->first();

        $this->assertNotNull($item);
        $this->assertSame(0, $item->free_amount);
        $this->assertSame(50, $item->paid_amount);
    }

    #[Test]
    public function 無償と有償アイテムを同時に加算できる(): void
    {
        // Execute
        $this->itemService->addItem(
            $this->sysPlayerId,
            'item_potion_001',
            freeAmount: 100,
            paidAmount: 50
        );

        // Save to DB
        $this->queryManager->execAllQuery();

        // Verify DB
        $item = DB::connection('trx1')
            ->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'item_potion_001')
            ->first();

        $this->assertNotNull($item);
        $this->assertSame(100, $item->free_amount);
        $this->assertSame(50, $item->paid_amount);
    }

    #[Test]
    public function 有償アイテムを優先的に消費する(): void
    {
        // Prepare: Add 100 free + 50 paid
        $this->itemService->addItem(
            $this->sysPlayerId,
            'item_potion_001',
            freeAmount: 100,
            paidAmount: 50
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume 30 (should consume from paid first)
        $result = $this->itemService->consumeItem(
            $this->sysPlayerId,
            'item_potion_001',
            30
        );
        $this->queryManager->execAllQuery();

        // Verify result
        $this->assertSame(100, $result->getFreeAmount()); // unchanged
        $this->assertSame(20, $result->getPaidAmount());  // 50 - 30

        // Verify DB
        $item = DB::connection('trx1')
            ->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'item_potion_001')
            ->first();

        $this->assertSame(100, $item->free_amount); // unchanged
        $this->assertSame(20, $item->paid_amount);  // 50 - 30
    }

    #[Test]
    public function 有償アイテムを使い切った後に無償アイテムを消費する(): void
    {
        // Prepare: Add 100 free + 50 paid
        $this->itemService->addItem(
            $this->sysPlayerId,
            'item_potion_001',
            freeAmount: 100,
            paidAmount: 50
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume 80 (50 from paid + 30 from free)
        $result = $this->itemService->consumeItem(
            $this->sysPlayerId,
            'item_potion_001',
            80
        );
        $this->queryManager->execAllQuery();

        // Verify result
        $this->assertSame(70, $result->getFreeAmount());  // 100 - 30
        $this->assertSame(0, $result->getPaidAmount());   // 50 - 50 = 0

        // Verify DB
        $item = DB::connection('trx1')
            ->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'item_potion_001')
            ->first();

        $this->assertSame(70, $item->free_amount);  // 100 - 30
        $this->assertSame(0, $item->paid_amount);   // 50 - 50 = 0
    }

    #[Test]
    public function 複数回加算すると合計される(): void
    {
        // First add
        $this->itemService->addItem(
            $this->sysPlayerId,
            'item_potion_001',
            freeAmount: 50,
            paidAmount: 20
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Second add
        $this->itemService->addItem(
            $this->sysPlayerId,
            'item_potion_001',
            freeAmount: 30,
            paidAmount: 10
        );
        $this->queryManager->execAllQuery();

        // Verify DB
        $item = DB::connection('trx1')
            ->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', 'item_potion_001')
            ->first();

        $this->assertSame(80, $item->free_amount);  // 50 + 30
        $this->assertSame(30, $item->paid_amount);  // 20 + 10
    }

    #[Test]
    public function 残高不足の場合は例外が発生する(): void
    {
        // Prepare: Add 10 items
        $this->itemService->addItem(
            $this->sysPlayerId,
            'item_potion_001',
            freeAmount: 10,
            paidAmount: 0
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute & Verify
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient item amount');

        $this->itemService->consumeItem(
            $this->sysPlayerId,
            'item_potion_001',
            20
        );
    }

    #[Test]
    public function 存在しないアイテムを消費すると例外が発生する(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Item not found');

        $this->itemService->consumeItem(
            $this->sysPlayerId,
            'item_potion_999',
            10
        );
    }

    #[Test]
    public function getItemAmountで合計数を取得できる(): void
    {
        // Prepare
        $this->itemService->addItem(
            $this->sysPlayerId,
            'item_potion_001',
            freeAmount: 100,
            paidAmount: 50
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute
        $amount = $this->itemService->getItemAmount($this->sysPlayerId, 'item_potion_001');

        // Verify
        $this->assertSame(150, $amount); // 100 + 50
    }

    #[Test]
    public function 存在しないアイテムのgetItemAmountは0を返す(): void
    {
        $amount = $this->itemService->getItemAmount($this->sysPlayerId, 'item_nonexistent');
        $this->assertSame(0, $amount);
    }

    #[Test]
    public function 有償アイテムのみを消費できる(): void
    {
        // Prepare: Add 100 free + 50 paid
        $this->itemService->addItem(
            $this->sysPlayerId,
            'item_potion_001',
            freeAmount: 100,
            paidAmount: 50
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume all paid (50)
        $result = $this->itemService->consumeItem(
            $this->sysPlayerId,
            'item_potion_001',
            50
        );
        $this->queryManager->execAllQuery();

        // Verify - free unchanged, paid becomes 0
        $this->assertSame(100, $result->getFreeAmount());
        $this->assertSame(0, $result->getPaidAmount());
    }

    #[Test]
    public function 大量のアイテムを正確に消費できる(): void
    {
        // Prepare: Add 10000 free + 5000 paid
        $this->itemService->addItem(
            $this->sysPlayerId,
            'item_potion_001',
            freeAmount: 10000,
            paidAmount: 5000
        );
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        // Execute: Consume 7000 (5000 from paid + 2000 from free)
        $result = $this->itemService->consumeItem(
            $this->sysPlayerId,
            'item_potion_001',
            7000
        );
        $this->queryManager->execAllQuery();

        // Verify
        $this->assertSame(8000, $result->getFreeAmount());  // 10000 - 2000
        $this->assertSame(0, $result->getPaidAmount());     // 5000 - 5000 = 0
        $this->assertSame(8000, $result->getTotalAmount());
    }
}
