<?php

namespace Tests\Feature\Domain\Item;

use App\Domain\Item\Services\ItemService;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * Wallet管理アイテムの振り分けのテスト
 *
 * mst_item.is_wallet が立っているアイテムは残高として持つため、
 * trx_item ではなく trx_wallet 系へ入れる。
 *
 * 振り分けは ItemService に集約しているので、呼び出し側は
 * アイテムか残高かを意識しない。両方が同じ入口を通ることが要点。
 */
class WalletManagedItemTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const WALLET_ITEM = 'gold';

    private const NORMAL_ITEM = 'item_potion_001';

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
        $this->makeMstItems();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();
        ClockUtility::reset();
        $this->queryManager->clear();

        parent::tearDown();
    }

    // ========================================
    // 振り分け
    // ========================================

    #[Test]
    public function マスターの定義でwallet管理かどうかが決まる(): void
    {
        $this->assertTrue($this->itemService->isWalletManaged(self::WALLET_ITEM));
        $this->assertFalse($this->itemService->isWalletManaged(self::NORMAL_ITEM));
    }

    #[Test]
    public function マスターに無いidはwallet管理ではない(): void
    {
        // 未定義のIDを残高側へ流すと、通貨として扱われてしまう
        $this->assertFalse($this->itemService->isWalletManaged('no_such_item'));
    }

    #[Test]
    public function wallet管理のアイテムはtrx_walletへ入る(): void
    {
        $this->itemService->addItem($this->sysPlayerId, self::WALLET_ITEM, freeAmount: 500);
        $this->flush();

        $this->assertSame(500, $this->walletAmount(self::WALLET_ITEM));
        $this->assertSame(0, $this->itemRowCount(self::WALLET_ITEM), 'trx_item には作らない');
    }

    #[Test]
    public function 通常のアイテムはtrx_itemへ入る(): void
    {
        $this->itemService->addItem($this->sysPlayerId, self::NORMAL_ITEM, freeAmount: 3);
        $this->flush();

        $this->assertSame(1, $this->itemRowCount(self::NORMAL_ITEM));
        $this->assertSame(0, $this->walletAmount(self::NORMAL_ITEM), 'trx_wallet には作らない');
    }

    #[Test]
    public function 所持数はどちらの入れ物からも同じ入口で取れる(): void
    {
        $this->itemService->addItem($this->sysPlayerId, self::WALLET_ITEM, freeAmount: 500);
        $this->itemService->addItem($this->sysPlayerId, self::NORMAL_ITEM, freeAmount: 3);
        $this->flush();

        $this->assertSame(500, $this->itemService->findItemAmount($this->sysPlayerId, self::WALLET_ITEM));
        $this->assertSame(3, $this->itemService->findItemAmount($this->sysPlayerId, self::NORMAL_ITEM));
    }

    #[Test]
    public function 持っていなければ0を返す(): void
    {
        $this->assertSame(0, $this->itemService->findItemAmount($this->sysPlayerId, self::WALLET_ITEM));
        $this->assertSame(0, $this->itemService->findItemAmount($this->sysPlayerId, self::NORMAL_ITEM));
    }

    #[Test]
    public function wallet管理のアイテムを消費できる(): void
    {
        $this->itemService->addItem($this->sysPlayerId, self::WALLET_ITEM, freeAmount: 500);
        $this->flush();

        $consumed = $this->itemService->consumeItem($this->sysPlayerId, self::WALLET_ITEM, 200);
        $this->flush();

        $this->assertSame(300, $this->walletAmount(self::WALLET_ITEM));
        $this->assertSame(300, $consumed->getFreeAmount(), '戻り値は消費後の残高');
    }

    #[Test]
    public function 有効期限を付けて残高を持てる(): void
    {
        // trx_item には有効期限が無い。Wallet に移すとここが使えるようになる
        $this->itemService->addItem($this->sysPlayerId, self::WALLET_ITEM, freeAmount: 100, expireAt: '2026-04-01 00:00:00');
        $this->flush();

        $balance = DB::connection($this->connection)->table('trx_wallet_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', self::WALLET_ITEM)
            ->first();

        $this->assertNotNull($balance);
        $this->assertSame('2026-04-01 00:00:00', $balance->expire_at);
    }

    // ========================================
    // 移植コマンド
    // ========================================

    #[Test]
    public function 既存のtrx_itemの残高をwalletへ移せる(): void
    {
        // Wallet管理に切り替える前に配ったぶん
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700, paidAmount: 300);

        $this->artisan('wallet:migrate-items', ['--item' => self::WALLET_ITEM])
            ->expectsOutputToContain('移行: 1 件（合計 1000）')
            ->assertExitCode(0);

        $this->assertSame(1000, $this->walletAmount(self::WALLET_ITEM));
        $this->assertSame(0, $this->itemRowCount(self::WALLET_ITEM), '移し終えた行は残さない');
    }

    #[Test]
    public function 有償と無償の内訳を保って移す(): void
    {
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700, paidAmount: 300);

        $this->artisan('wallet:migrate-items')->assertExitCode(0);

        $wallet = $this->walletRow(self::WALLET_ITEM);
        $this->assertSame(700, (int) $wallet->free_amount);
        $this->assertSame(300, (int) $wallet->paid_amount);
    }

    #[Test]
    public function 移した残高は無期限の取得単位として入る(): void
    {
        // trx_item に有効期限が無いため、既存ぶんは期限を付けない
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700, paidAmount: 300);

        $this->artisan('wallet:migrate-items')->assertExitCode(0);

        $balances = DB::connection($this->connection)->table('trx_wallet_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', self::WALLET_ITEM)
            ->orderBy('is_paid')
            ->get();

        $this->assertCount(2, $balances, '無償と有償で分けて入る');
        $this->assertSame(700, (int) $balances[0]->current_amount);
        $this->assertSame(300, (int) $balances[1]->current_amount);
        foreach ($balances as $balance) {
            $this->assertNull($balance->expire_at);
            $this->assertSame((int) $balance->initial_amount, (int) $balance->current_amount);
        }
    }

    #[Test]
    public function 既にwalletに残高があれば足し込む(): void
    {
        // 切り替え後に配られたぶんと、切り替え前のぶんが両方ある状態
        $this->itemService->addItem($this->sysPlayerId, self::WALLET_ITEM, freeAmount: 100);
        $this->flush();
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700);

        $this->artisan('wallet:migrate-items')->assertExitCode(0);

        $this->assertSame(800, $this->walletAmount(self::WALLET_ITEM), '上書きせず足す');
    }

    #[Test]
    public function wallet管理でないアイテムは移さない(): void
    {
        $this->makeTrxItem(self::NORMAL_ITEM, freeAmount: 5);

        $this->artisan('wallet:migrate-items')->assertExitCode(0);

        $this->assertSame(1, $this->itemRowCount(self::NORMAL_ITEM), '通常アイテムはそのまま');
        $this->assertSame(0, $this->walletAmount(self::NORMAL_ITEM));
    }

    #[Test]
    public function dry_runでは移さない(): void
    {
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700);

        $this->artisan('wallet:migrate-items', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY RUN モード]')
            ->expectsOutputToContain('移行: 1 件（合計 700）')
            ->assertExitCode(0);

        $this->assertSame(1, $this->itemRowCount(self::WALLET_ITEM));
        $this->assertSame(0, $this->walletAmount(self::WALLET_ITEM));
    }

    #[Test]
    public function 対象が無ければ何もせず終わる(): void
    {
        $this->artisan('wallet:migrate-items')
            ->expectsOutputToContain('移す対象の残高はありませんでした')
            ->assertExitCode(0);
    }

    #[Test]
    public function wallet管理でないアイテムは指定できない(): void
    {
        $this->artisan('wallet:migrate-items', ['--item' => self::NORMAL_ITEM])
            ->expectsOutputToContain('Wallet管理のアイテムではありません')
            ->assertExitCode(0);
    }

    #[Test]
    public function 論理削除済みの行は移さない(): void
    {
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700, isDelete: true);

        $this->artisan('wallet:migrate-items')->assertExitCode(0);

        $this->assertSame(0, $this->walletAmount(self::WALLET_ITEM));
    }

    private function flush(): void
    {
        $this->queryManager->execAllQuery();
        $this->queryManager->clear();
    }

    private function walletAmount(string $mstItemId): int
    {
        $row = $this->walletRow($mstItemId);

        return $row ? (int) $row->free_amount + (int) $row->paid_amount : 0;
    }

    private function walletRow(string $mstItemId): ?object
    {
        return DB::connection($this->connection)->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();
    }

    private function itemRowCount(string $mstItemId): int
    {
        return DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->count();
    }

    private function makeTrxItem(
        string $mstItemId,
        int $freeAmount = 0,
        int $paidAmount = 0,
        bool $isDelete = false,
    ): void {
        DB::connection($this->connection)->table('trx_item')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'mst_item_id' => $mstItemId,
            'free_amount' => $freeAmount,
            'paid_amount' => $paidAmount,
            'is_delete' => $isDelete,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeMstItems(): void
    {
        DB::connection('mst')->table('mst_item')->insert([
            [
                'id' => self::WALLET_ITEM,
                'type' => 'Currency',
                'effect' => 'None',
                'value' => 0,
                'is_wallet' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => self::NORMAL_ITEM,
                'type' => 'Recovery',
                'effect' => 'HealHP',
                'value' => 100,
                'is_wallet' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->refreshMstCache();
    }

    private function cleanUp(): void
    {
        foreach (['trx_item', 'trx_wallet', 'trx_wallet_balance'] as $table) {
            DB::connection($this->connection)->table($table)
                ->where('sys_player_id', $this->sysPlayerId)->delete();
        }

        DB::connection('mst')->table('mst_item')
            ->whereIn('id', [self::WALLET_ITEM, self::NORMAL_ITEM])->delete();

        $this->refreshMstCache();
    }
}
