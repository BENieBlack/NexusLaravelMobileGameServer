<?php

namespace Tests\Feature\Domain\Item\Services;

use App\Domain\Item\Services\ItemService;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;
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
        $this->useSessionPlayer($this->sysPlayerId);

        $this->itemService = app(ItemService::class);
        $this->queryManager = app(QueryManager::class);
    }

    protected function tearDown(): void
    {
        // Clear all test data
        DB::connection('trx1')->table('trx_item')->delete();

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
        $this->useSessionPlayer($this->sysPlayerId);

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
        $this->useSessionPlayer($this->sysPlayerId);

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
        $this->useSessionPlayer($this->sysPlayerId);

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
        $this->useSessionPlayer($this->sysPlayerId);

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
    public function get_item_amountで合計数を取得できる(): void
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
        $this->useSessionPlayer($this->sysPlayerId);

        // Execute
        $amount = $this->itemService->findItemAmount($this->sysPlayerId, 'item_potion_001');

        // Verify
        $this->assertSame(150, $amount); // 100 + 50
    }

    #[Test]
    public function 存在しないアイテムのget_item_amountは0を返す(): void
    {
        $amount = $this->itemService->findItemAmount($this->sysPlayerId, 'item_nonexistent');
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
        $this->useSessionPlayer($this->sysPlayerId);

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
        $this->useSessionPlayer($this->sysPlayerId);

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

    #[Test]
    public function 無償ダイヤは通常のアイテム消費でinvalid_item_typeになり何も変えない(): void
    {
        $this->seedFreeDiamond($this->sysPlayerId, 100);
        ApiSession::clearForTest();
        $this->useSessionPlayer($this->sysPlayerId);

        try {
            $this->itemService->consumeItem($this->sysPlayerId, 'free_diamond', 10);
            $this->fail('free_diamond の消費は拒否されるはず');
        } catch (GameException $e) {
            $this->assertSame(GameErrorCode::INVALID_ITEM_TYPE, $e->getErrorCode());
        }

        // 何も書かれていない（キューが空で、DB も 100 のまま）
        $this->queryManager->execAllQuery();
        $this->assertSame(100, (int) DB::connection('trx1')->table('trx_item')
            ->where('mst_item_id', 'free_diamond')->value('free_amount'));
    }

    #[Test]
    public function 無償ダイヤのガードはwalletの分岐より前に効き_walletへの移行を起こさない(): void
    {
        // 万一 mst_item.is_wallet が立っても、Wallet の移行（trx_item の行を Wallet へ移す副作用）より前に拒否する
        $original = DB::connection('mst')->table('mst_item')->where('id', 'free_diamond')->first();
        DB::connection('mst')->table('mst_item')->updateOrInsert(
            ['id' => 'free_diamond'],
            ['type' => 'currency', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
        );
        $this->refreshMstCache();
        $this->seedFreeDiamond($this->sysPlayerId, 100);
        ApiSession::clearForTest();
        $this->useSessionPlayer($this->sysPlayerId);

        try {
            $this->itemService->consumeItem($this->sysPlayerId, 'free_diamond', 10);
            $this->fail('free_diamond の消費は拒否されるはず');
        } catch (GameException $e) {
            $this->assertSame(GameErrorCode::INVALID_ITEM_TYPE, $e->getErrorCode());
        } finally {
            if ($original === null) {
                DB::connection('mst')->table('mst_item')->where('id', 'free_diamond')->delete();
            } else {
                DB::connection('mst')->table('mst_item')->where('id', 'free_diamond')->update(['is_wallet' => (bool) $original->is_wallet]);
            }
            $this->refreshMstCache();
        }

        $this->queryManager->execAllQuery();
        $this->assertSame(100, (int) DB::connection('trx1')->table('trx_item')
            ->where('mst_item_id', 'free_diamond')->value('free_amount'));
        $this->assertSame(0, DB::connection('trx1')->table('trx_wallet')->where('mst_item_id', 'free_diamond')->count());
    }

    #[Test]
    public function 無償ダイヤの付与と所持数の参照はガードされない(): void
    {
        $this->itemService->addItem($this->sysPlayerId, 'free_diamond', freeAmount: 30);
        $this->queryManager->execAllQuery();

        ApiSession::clearForTest();
        $this->useSessionPlayer($this->sysPlayerId);

        $this->assertSame(30, $this->itemService->findItemAmount($this->sysPlayerId, 'free_diamond'));
    }

    #[Test]
    public function 無償ダイヤ以外のアイテムは従来どおり消費できる(): void
    {
        $this->itemService->addItem($this->sysPlayerId, 'item_potion_001', freeAmount: 5);
        $this->queryManager->execAllQuery();
        ApiSession::clearForTest();
        $this->useSessionPlayer($this->sysPlayerId);

        $result = $this->itemService->consumeItem($this->sysPlayerId, 'item_potion_001', 2);

        $this->assertSame(3, $result->getFreeAmount());
    }
}
