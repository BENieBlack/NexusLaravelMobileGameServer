<?php

namespace Tests\Feature\Domain\Item;

use App\Domain\Item\Services\ItemService;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusResource\DataTransferObjects\Resource;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Handlers\ItemDeliveryHandler;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * Wallet管理アイテムを配送したときの入り先のテスト
 *
 * gold のような残高を mst_item として定義できるようにしたため、
 * メールボックスやガチャの中身が content_type=item のまま
 * それらを指せるようになった。
 *
 * その経路がパッケージ層のItemServiceを直接呼んでいると
 * mst_item.is_wallet の振り分けを通らず、残高が trx_item へ入る。
 * 所持数はWallet側を見るので、プレイヤーからは受け取れていないように見える。
 */
class WalletItemDeliveryTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const WALLET_ITEM = 'gold';

    private const NORMAL_ITEM = 'item_potion_001';

    private int $sysPlayerId;

    private string $connection;

    private ItemDeliveryHandler $handler;

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

        $this->handler = app(ItemDeliveryHandler::class);
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

    #[Test]
    public function item型で配送してもwallet管理ならwalletへ入る(): void
    {
        $this->deliver(self::WALLET_ITEM, 100);

        $this->assertSame(100, $this->walletAmount(self::WALLET_ITEM));
        $this->assertSame(0, $this->itemRowCount(self::WALLET_ITEM), 'trx_item に入れてはいけない');
    }

    #[Test]
    public function 配送したwallet管理の残高は所持数として見える(): void
    {
        // 入り先と読み先がずれると、受け取ったのに0のままになる
        $this->deliver(self::WALLET_ITEM, 100);

        $this->assertSame(100, $this->itemService->findItemAmount($this->sysPlayerId, self::WALLET_ITEM));
    }

    #[Test]
    public function 通常のアイテムはtrx_itemへ入る(): void
    {
        $this->deliver(self::NORMAL_ITEM, 3);

        $this->assertSame(1, $this->itemRowCount(self::NORMAL_ITEM));
        $this->assertSame(0, $this->walletAmount(self::NORMAL_ITEM));
        $this->assertSame(3, $this->itemService->findItemAmount($this->sysPlayerId, self::NORMAL_ITEM));
    }

    #[Test]
    public function 配送を重ねると加算される(): void
    {
        $this->deliver(self::WALLET_ITEM, 100);
        $this->deliver(self::WALLET_ITEM, 50);

        $this->assertSame(150, $this->walletAmount(self::WALLET_ITEM));
    }

    #[Test]
    public function 有効期限付きで配送できる(): void
    {
        $this->deliver(self::WALLET_ITEM, 100, expireAt: '2026-04-01 00:00:00');

        $balance = DB::connection($this->connection)->table('trx_wallet_balance')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', self::WALLET_ITEM)
            ->first();

        $this->assertNotNull($balance);
        $this->assertSame('2026-04-01 00:00:00', $balance->expire_at);
    }

    private function deliver(string $mstItemId, int $amount, ?string $expireAt = null): void
    {
        $this->handler->handle($this->sysPlayerId, new ResourceDeliveryContent(
            new Resource(ResourceType::ITEM, $mstItemId, $amount, $expireAt)
        ));

        $this->queryManager->execAllQuery();
        $this->queryManager->clear();
    }

    private function walletAmount(string $mstItemId): int
    {
        $row = DB::connection($this->connection)->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        return $row ? (int) $row->free_amount + (int) $row->paid_amount : 0;
    }

    private function itemRowCount(string $mstItemId): int
    {
        return DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->count();
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
