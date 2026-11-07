<?php

namespace Tests\Feature\Domain\Item;

use App\Domain\Item\Services\ItemService;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 1回の処理（トランザクション）の中で、同じアイテム・通貨を続けて増減するテスト
 *
 * 加算・消費は、まずメモリに積み、コミット時にDBへ書く。
 * 2回目の読み取りがDBだけを見ると、1回目の結果を見ずに古い値から計算して、1回目の分が消える。
 */
class SameTransactionItemTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const WALLET_ITEM = 'same_tx_gold';

    private const NORMAL_ITEM = 'same_tx_potion';

    private int $sysPlayerId;

    private string $connection;

    private ItemService $itemService;

    private QueryManager $queryManager;

    public function beginDatabaseTransaction(): void
    {
        // QueryManagerで明示的にフラッシュするため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');

        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        $this->connection = $this->playerConnection($this->sysPlayerId);
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->itemService = app(ItemService::class);
        $this->queryManager = app(QueryManager::class);

        $this->cleanUp();
        DB::connection('mst')->table('mst_item')->insert([
            ['id' => self::WALLET_ITEM, 'type' => 'Currency', 'effect' => 'None', 'value' => 0, 'is_wallet' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => self::NORMAL_ITEM, 'type' => 'Recovery', 'effect' => 'HealHP', 'value' => 100, 'is_wallet' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->refreshMstCache();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();
        ClockUtility::reset();
        $this->queryManager->clear();

        parent::tearDown();
    }

    #[Test]
    public function アイテムを続けて消費すると両方が反映される(): void
    {
        $this->itemService->addItem($this->sysPlayerId, self::NORMAL_ITEM, freeAmount: 10);
        $this->flush();

        $this->itemService->consumeItem($this->sysPlayerId, self::NORMAL_ITEM, 3);
        $this->itemService->consumeItem($this->sysPlayerId, self::NORMAL_ITEM, 4);
        $this->flush();

        $this->assertSame(3, $this->itemAmount());
    }

    #[Test]
    public function 消費した直後の所持数は消費後の値を返す(): void
    {
        $this->itemService->addItem($this->sysPlayerId, self::NORMAL_ITEM, freeAmount: 10);
        $this->flush();

        $this->itemService->consumeItem($this->sysPlayerId, self::NORMAL_ITEM, 4);

        // まだコミットしていなくても、同じ処理の中では消費後の値が見える
        $this->assertSame(6, $this->itemService->findItemAmount($this->sysPlayerId, self::NORMAL_ITEM));
        $this->assertSame(6, app(\Nexus\Core\Resource\Services\ItemService::class)->findItemAmounts($this->sysPlayerId, [self::NORMAL_ITEM])[self::NORMAL_ITEM]);
    }

    #[Test]
    public function 付与と消費を続けても反映される(): void
    {
        $this->itemService->addItem($this->sysPlayerId, self::NORMAL_ITEM, freeAmount: 5);
        $this->flush();

        $this->itemService->addItem($this->sysPlayerId, self::NORMAL_ITEM, freeAmount: 5);
        $this->itemService->consumeItem($this->sysPlayerId, self::NORMAL_ITEM, 2);
        $this->flush();

        $this->assertSame(8, $this->itemAmount());
    }

    #[Test]
    public function 通貨を続けて消費すると現在値も残高の内訳も合う(): void
    {
        $this->itemService->addItem($this->sysPlayerId, self::WALLET_ITEM, freeAmount: 100);
        $this->flush();

        $this->itemService->consumeItem($this->sysPlayerId, self::WALLET_ITEM, 30);
        $this->itemService->consumeItem($this->sysPlayerId, self::WALLET_ITEM, 40);
        $this->flush();

        $wallet = DB::connection($this->connection)->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', self::WALLET_ITEM)->first();
        $this->assertSame(30, (int) $wallet->free_amount + (int) $wallet->paid_amount);

        // 内訳（FIFOの残高）の合計も、現在値と一致する
        $balances = (int) DB::connection($this->connection)->table('trx_wallet_balance')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', self::WALLET_ITEM)->sum('current_amount');
        $this->assertSame(30, $balances);
    }

    private function itemAmount(): int
    {
        $row = DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', self::NORMAL_ITEM)->first();

        return $row ? (int) $row->free_amount + (int) $row->paid_amount : 0;
    }

    private function flush(): void
    {
        $this->queryManager->execAllQuery();
        $this->queryManager->clear();
    }

    private function cleanUp(): void
    {
        foreach (['trx_item', 'trx_wallet', 'trx_wallet_balance'] as $table) {
            DB::connection($this->connection ?? $this->playerConnection($this->sysPlayerId ?? 0))->table($table)
                ->where('sys_player_id', $this->sysPlayerId ?? 0)->delete();
        }

        DB::connection('mst')->table('mst_item')->whereIn('id', [self::WALLET_ITEM, self::NORMAL_ITEM])->delete();
        $this->refreshMstCache();
    }
}
