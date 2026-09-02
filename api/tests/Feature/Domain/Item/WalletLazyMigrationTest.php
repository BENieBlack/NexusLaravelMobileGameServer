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
 * Wallet管理へ切り替えたアイテムの遅延移行のテスト
 *
 * リリース後に mst_item.is_wallet を立てると、読み書きの先が
 * その瞬間から Wallet に変わる。trx_item に残っている残高は
 * 参照されなくなり、プレイヤーからは消えて見える。
 *
 * 触られた時点でその場で移すことで、この窓を無くす。
 * 一括移行コマンドを流す前でも残高が見えることが要点。
 */
class WalletLazyMigrationTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const WALLET_ITEM = 'gold';

    private const NORMAL_ITEM = 'item_potion_001';

    private int $sysPlayerId;

    private string $connection;

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

    #[Test]
    public function 所持数を見た時点で残高がwalletへ移る(): void
    {
        // is_wallet を立てる前に配ったぶん
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700, paidAmount: 300);

        $amount = $this->itemService()->findItemAmount($this->sysPlayerId, self::WALLET_ITEM);
        $this->flush();

        $this->assertSame(1000, $amount, '移行コマンドを流す前でも見えること');
        $this->assertSame(1000, $this->walletAmount(self::WALLET_ITEM));
        $this->assertSame(0, $this->itemRowCount(self::WALLET_ITEM), '移した行は残さない');
    }

    #[Test]
    public function 有償と無償の内訳を保って移る(): void
    {
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700, paidAmount: 300);

        $this->itemService()->findItemAmount($this->sysPlayerId, self::WALLET_ITEM);
        $this->flush();

        $wallet = $this->walletRow(self::WALLET_ITEM);
        $this->assertSame(700, (int) $wallet->free_amount);
        $this->assertSame(300, (int) $wallet->paid_amount);
    }

    #[Test]
    public function 移った残高は無期限の取得単位として入る(): void
    {
        // trx_item に有効期限が無いため、既存ぶんは期限を付けない
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700, paidAmount: 300);

        $this->itemService()->findItemAmount($this->sysPlayerId, self::WALLET_ITEM);
        $this->flush();

        $balances = DB::connection($this->connection)->table('trx_wallet_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', self::WALLET_ITEM)
            ->orderBy('is_paid')
            ->get();

        $this->assertCount(2, $balances);
        $this->assertSame(700, (int) $balances[0]->current_amount);
        $this->assertSame(300, (int) $balances[1]->current_amount);
        foreach ($balances as $balance) {
            $this->assertNull($balance->expire_at);
        }
    }

    #[Test]
    public function 付与した時点でも移る(): void
    {
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700);

        $this->itemService()->addItem($this->sysPlayerId, self::WALLET_ITEM, freeAmount: 100);
        $this->flush();

        $this->assertSame(800, $this->walletAmount(self::WALLET_ITEM), '旧残高に足し込む');
        $this->assertSame(0, $this->itemRowCount(self::WALLET_ITEM));
    }

    #[Test]
    public function 消費した時点でも移る(): void
    {
        // 移る前に消費しようとすると残高不足で落ちる
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700);

        $this->itemService()->consumeItem($this->sysPlayerId, self::WALLET_ITEM, 200);
        $this->flush();

        $this->assertSame(500, $this->walletAmount(self::WALLET_ITEM));
        $this->assertSame(0, $this->itemRowCount(self::WALLET_ITEM));
    }

    #[Test]
    public function 同じリクエストで二重に移さない(): void
    {
        // 削除はキューに積まれるだけなので、記憶しないと
        // 読むたびに同じ行を見つけて二重に足してしまう
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700);

        $service = $this->itemService();
        $service->findItemAmount($this->sysPlayerId, self::WALLET_ITEM);
        $service->addItem($this->sysPlayerId, self::WALLET_ITEM, freeAmount: 100);
        $service->findItemAmount($this->sysPlayerId, self::WALLET_ITEM);
        $this->flush();

        $this->assertSame(800, $this->walletAmount(self::WALLET_ITEM));
    }

    #[Test]
    public function 別インスタンスから触っても二重に移さない(): void
    {
        // ItemService はUseCaseごとに作られる。記憶はリクエスト単位で共有する
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700);

        $this->itemService()->findItemAmount($this->sysPlayerId, self::WALLET_ITEM);
        app()->make(ItemService::class)->findItemAmount($this->sysPlayerId, self::WALLET_ITEM);
        $this->flush();

        $this->assertSame(700, $this->walletAmount(self::WALLET_ITEM));
    }

    #[Test]
    public function 既にwalletに残高があれば足し込む(): void
    {
        // 切り替え後に配られたぶんと、切り替え前のぶんが両方ある状態
        $this->itemService()->addItem($this->sysPlayerId, self::WALLET_ITEM, freeAmount: 100);
        $this->nextRequest();
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700);

        $this->assertSame(800, $this->itemService()->findItemAmount($this->sysPlayerId, self::WALLET_ITEM));
    }

    #[Test]
    public function 移り終えた後は残高がそのまま読める(): void
    {
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700);

        $this->itemService()->findItemAmount($this->sysPlayerId, self::WALLET_ITEM);
        $this->nextRequest();

        $this->assertSame(700, $this->itemService()->findItemAmount($this->sysPlayerId, self::WALLET_ITEM));
        $this->assertSame(700, $this->walletAmount(self::WALLET_ITEM), '二度目で増えていない');
    }

    #[Test]
    public function 残高が0の行も消す(): void
    {
        // 残すと読むたびに移行の判定を通る
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 0);

        $this->itemService()->findItemAmount($this->sysPlayerId, self::WALLET_ITEM);
        $this->flush();

        $this->assertSame(0, $this->itemRowCount(self::WALLET_ITEM));
        $this->assertNull($this->walletRow(self::WALLET_ITEM), '0を足すだけの行は作らない');
    }

    #[Test]
    public function 論理削除済みの行は移さない(): void
    {
        $this->makeTrxItem(self::WALLET_ITEM, freeAmount: 700, isDelete: true);

        $this->itemService()->findItemAmount($this->sysPlayerId, self::WALLET_ITEM);
        $this->flush();

        $this->assertSame(0, $this->walletAmount(self::WALLET_ITEM));
        $this->assertSame(1, $this->itemRowCount(self::WALLET_ITEM), '触らずに残す');
    }

    #[Test]
    public function wallet管理でないアイテムは移さない(): void
    {
        $this->makeTrxItem(self::NORMAL_ITEM, freeAmount: 5);

        $this->assertSame(5, $this->itemService()->findItemAmount($this->sysPlayerId, self::NORMAL_ITEM));
        $this->flush();

        $this->assertSame(1, $this->itemRowCount(self::NORMAL_ITEM));
        $this->assertSame(0, $this->walletAmount(self::NORMAL_ITEM));
    }

    private function itemService(): ItemService
    {
        return app(ItemService::class);
    }

    /**
     * 次のリクエストに入ったことにする
     *
     * 移行済みの記憶はリクエスト単位（scoped）で持つため、
     * 捨てないと同一プロセスのテストでは持ち越してしまう
     */
    private function nextRequest(): void
    {
        $this->flush();

        // scopedを捨てるとApiSessionとQueryManagerも作り直しになる
        app()->forgetScopedInstances();
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $this->queryManager = app(QueryManager::class);
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
            ],
            [
                'id' => self::NORMAL_ITEM,
                'type' => 'Recovery',
                'effect' => 'HealHP',
                'value' => 100,
                'is_wallet' => false,
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
