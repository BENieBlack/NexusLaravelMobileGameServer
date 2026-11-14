<?php

namespace Tests\Feature\Trade;

use App\Exceptions\GameErrorCode;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 交換所のエンドポイントのテスト
 *
 * ダイヤ・コイン（Wallet管理の通貨）・アイテムを消費して、アイテムと交換できること、
 * 足りないときや上限・期間外では何も変わらないことを固定する。
 */
class TradeEndpointTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const TRADE_IDS = ['trade_test_general', 'trade_test_closed'];

    private const PRODUCT_IDS = [
        'product_by_diamond', 'product_by_paid_diamond', 'product_by_coin', 'product_by_item',
        'product_daily_limited', 'product_expired', 'product_in_closed_trade',
    ];

    private const COIN_ID = 'coin_trade_test';

    private const TICKET_ID = 'ticket_trade_test';

    private const REWARD_ID = 'reward_trade_test';

    private int $sysPlayerId;

    private string $accessToken;

    private string $connection;

    public function beginDatabaseTransaction(): void
    {
        // UseCaseが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setDayStartTime('04:00:00');
        ClockUtility::setNow('2026-10-15 12:00:00');

        $this->cleanUpMaster();
        $this->createMaster();
        $this->refreshMstCache();

        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        $this->accessToken = $token;
        $this->connection = $this->playerConnection($this->sysPlayerId);

        // 空の新規プレイヤーを前提にフィクスチャを入れるので、初期付与の行を外す
        $this->deleteInitialGrant($this->sysPlayerId);
        $this->createPlayerData();
        ApiSession::clearForTest();
        app(QueryManager::class)->clear();
    }

    protected function tearDown(): void
    {
        foreach (['trx_trade_product', 'trx_diamond', 'trx_diamond_balance', 'trx_item', 'trx_wallet', 'trx_wallet_balance', 'log_action_trade'] as $table) {
            DB::connection($this->connection)->table($table)
                ->where('sys_player_id', $this->sysPlayerId)->delete();
        }

        $this->cleanUpMaster();
        $this->refreshMstCache();
        ApiSession::clearForTest();
        ClockUtility::reset();
        ClockUtility::setDayStartTime('00:00:00');
        app(QueryManager::class)->clear();

        parent::tearDown();
    }

    #[Test]
    public function 開催中の交換所にある交換期間内の商品だけが並ぶ(): void
    {
        $response = $this->getJson('/api/trade/list', $this->authHeaders($this->accessToken));

        $response->assertOk();
        $this->assertSame(
            ['product_by_diamond', 'product_by_paid_diamond', 'product_by_coin', 'product_by_item', 'product_daily_limited'],
            array_column($response->json('trade_product_list'), 'mst_trade_product_id'),
        );
        $this->assertSame([
            'mst_trade_product_id' => 'product_daily_limited',
            'mst_trade_id' => 'trade_test_general',
            'cost_type' => 'item',
            'cost_mst_id' => self::TICKET_ID,
            'cost_amount' => 1,
            'trade_limit' => 1,
            'traded_count' => 0,
            'remaining_count' => 1,
            'reset_type' => 'daily',
            'end_at' => null,
            'content_list' => [['content_type' => 'item', 'content_mst_id' => self::REWARD_ID, 'content_quantity' => 1]],
        ], $response->json('trade_product_list.4'));
    }

    #[Test]
    public function ダイヤを消費してアイテムと交換できる(): void
    {
        $response = $this->exchange('product_by_diamond', quantity: 3, headers: ['X-Unique-Request-Identifier' => 'req-trade-1']);

        $response->assertOk();
        $this->assertSame(3, $response->json('trade_product.traded_count'));

        // 消費した無償ダイヤ（trx_item の free_diamond）と受け取ったアイテムが、更新した trx として返る。
        // 有償は変わっていないので trx_diamond は載らない
        $items = collect($response->json('updated_resources.trx_item_list'))->keyBy('mst_item_id');
        $this->assertSame(10, $items['free_diamond']['free_amount']);
        $this->assertSame(6, $items[self::REWARD_ID]['free_amount']);
        $this->assertArrayNotHasKey('trx_diamond', $response->json('updated_resources'));
        $this->assertArrayNotHasKey('trx_diamond_list', $response->json('updated_resources'));

        // 無償ダイヤ 100 から 30 × 3 = 90 を消費。報酬は 2 × 3 = 6 個
        $this->assertSame(['free' => 10, 'paid' => 50], $this->diamondAmount());
        $this->assertSame(6, $this->itemAmount(self::REWARD_ID));

        $log = DB::connection($this->connection)->table('log_action_trade')
            ->where('sys_player_id', $this->sysPlayerId)->first();
        $this->assertSame('req-trade-1', $log->unique_request_id);
        $this->assertSame('product_by_diamond', $log->mst_trade_product_id);
        $this->assertSame(3, (int) $log->quantity);
        $this->assertSame(90, (int) $log->cost_amount);
    }

    #[Test]
    public function 有償ダイヤだけの商品は無償ダイヤでは交換できない(): void
    {
        // 有償ダイヤは 50 しかない（無償の 100 は数えない）
        $this->assertBusinessError($this->exchange('product_by_paid_diamond', quantity: 1), GameErrorCode::DIAMOND_NOT_ENOUGH);

        $this->assertSame(['free' => 100, 'paid' => 50], $this->diamondAmount());
        $this->assertSame(0, $this->itemAmount(self::REWARD_ID));
    }

    #[Test]
    public function 無償を使い切って有償にまたがると両方が更新され_balanceも連動する(): void
    {
        // コストを 120 にする: 無償 100 + 有償 20
        DB::connection('mst')->table('mst_trade_product')->where('id', 'product_by_diamond')->update(['cost_amount' => 120]);
        $this->refreshMstCache();

        $response = $this->exchange('product_by_diamond', quantity: 1);

        $response->assertOk();
        $this->assertSame(['free' => 0, 'paid' => 30], $this->diamondAmount());
        $this->assertSame(30, $this->balanceTotal());
        // 無償の行は 0 で残る
        $this->assertSame(1, DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', 'free_diamond')->count());

        // 両方載る（trx_diamond は単数のオブジェクト。free_amount は無い）
        $items = collect($response->json('updated_resources.trx_item_list'))->keyBy('mst_item_id');
        $this->assertSame(0, $items['free_diamond']['free_amount']);
        $this->assertSame('Apple', $response->json('updated_resources.trx_diamond.platform'));
        $this->assertSame(30, $response->json('updated_resources.trx_diamond.paid_amount'));
        $this->assertArrayNotHasKey('free_amount', $response->json('updated_resources.trx_diamond'));
    }

    #[Test]
    public function 有償だけの商品は有償と_balanceだけを減らし無償は触らない(): void
    {
        DB::connection('mst')->table('mst_trade_product')->where('id', 'product_by_paid_diamond')->update(['cost_amount' => 20]);
        $this->refreshMstCache();

        $response = $this->exchange('product_by_paid_diamond', quantity: 1);

        $response->assertOk();
        $this->assertSame(['free' => 100, 'paid' => 30], $this->diamondAmount());
        $this->assertSame(30, $this->balanceTotal());
        $this->assertSame(30, $response->json('updated_resources.trx_diamond.paid_amount'));
        $freeRows = collect($response->json('updated_resources.trx_item_list'))->where('mst_item_id', 'free_diamond');
        $this->assertCount(0, $freeRows);
    }

    #[Test]
    public function 他platformの有償だけでは交換できない(): void
    {
        // Apple の有償 50 を消し、Google の有償 500 だけにする。無償 100 では 120 に足りない
        DB::connection($this->connection)->table('trx_diamond')->where('sys_player_id', $this->sysPlayerId)->delete();
        DB::connection($this->connection)->table('trx_diamond_balance')->where('sys_player_id', $this->sysPlayerId)->delete();
        $this->seedPaidDiamond($this->sysPlayerId, 'Google', 500);
        DB::connection('mst')->table('mst_trade_product')->where('id', 'product_by_diamond')->update(['cost_amount' => 120]);
        $this->refreshMstCache();

        $this->assertBusinessError($this->exchange('product_by_diamond', quantity: 1), GameErrorCode::DIAMOND_NOT_ENOUGH);

        $this->assertSame(100, (int) DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', 'free_diamond')->value('free_amount'));
        $this->assertSame(500, (int) DB::connection($this->connection)->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)->where('platform', 'Google')->value('paid_amount'));
    }

    #[Test]
    public function コストitemで無償ダイヤを指す商品は拒否され何も変わらない(): void
    {
        DB::connection('mst')->table('mst_trade_product')->where('id', 'product_by_item')
            ->update(['cost_mst_id' => 'free_diamond']);
        $this->refreshMstCache();

        $this->assertBusinessError($this->exchange('product_by_item', quantity: 1), GameErrorCode::INVALID_ITEM_TYPE);

        $this->assertSame(['free' => 100, 'paid' => 50], $this->diamondAmount());
        $this->assertSame(0, $this->itemAmount(self::REWARD_ID));
    }

    #[Test]
    public function コインを消費して交換できる(): void
    {
        $this->exchange('product_by_coin', quantity: 2)->assertOk();

        // コイン 500 から 200 × 2 = 400 を消費
        $this->assertSame(100, (int) DB::connection($this->connection)->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', self::COIN_ID)->value('free_amount'));
        $this->assertSame(2, $this->itemAmount(self::REWARD_ID));
    }

    #[Test]
    public function アイテムが足りなければ何も変わらない(): void
    {
        // チケットは 3 枚、5 × 1 = 5 枚必要
        $this->assertBusinessError($this->exchange('product_by_item', quantity: 1), GameErrorCode::ITEM_NOT_ENOUGH);

        $this->assertSame(3, $this->itemAmount(self::TICKET_ID));
        $this->assertSame(0, $this->itemAmount(self::REWARD_ID));
        $this->assertSame(0, DB::connection($this->connection)->table('trx_trade_product')
            ->where('sys_player_id', $this->sysPlayerId)->count());
    }

    #[Test]
    public function 毎日の上限に達したら翌日まで交換できない(): void
    {
        $this->exchange('product_daily_limited', quantity: 1)->assertOk()
            ->assertJsonPath('trade_product.remaining_count', 0);

        $this->assertBusinessError($this->exchange('product_daily_limited', quantity: 1), GameErrorCode::TRADE_LIMIT_EXCEEDED);

        // ゲーム内日付が変わる（04:00）と数え直す
        ClockUtility::setNow('2026-10-16 04:00:00');
        $this->exchange('product_daily_limited', quantity: 1)->assertOk()
            ->assertJsonPath('trade_product.traded_count', 1);
        $this->assertSame(1, $this->itemAmount(self::TICKET_ID));
    }

    #[Test]
    public function 交換期間外や存在しない商品は交換できない(): void
    {
        $this->assertBusinessError($this->exchange('product_expired', quantity: 1), GameErrorCode::TRADE_PRODUCT_NOT_OPEN);
        $this->assertBusinessError($this->exchange('product_in_closed_trade', quantity: 1), GameErrorCode::TRADE_PRODUCT_NOT_OPEN);
        $this->assertBusinessError($this->exchange('product_missing', quantity: 1), GameErrorCode::TRADE_PRODUCT_NOT_FOUND);
    }

    #[Test]
    public function 個数は1以上でなければならない(): void
    {
        $this->exchange('product_by_diamond', quantity: 0)->assertStatus(422);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function exchange(string $mstTradeProductId, int $quantity, array $headers = []): TestResponse
    {
        return $this->postJson(
            '/api/trade/exchange',
            ['mst_trade_product_id' => $mstTradeProductId, 'quantity' => $quantity],
            [...$this->authHeaders($this->accessToken), ...$headers],
        );
    }

    private function assertBusinessError(TestResponse $response, int $errorCode): void
    {
        $response->assertStatus(299);
        $this->assertSame($errorCode, $response->json('error_code'));
    }

    /**
     * @return array{free: int, paid: int}
     */
    private function diamondAmount(): array
    {
        // 無償は trx_item の free_diamond、有償は X-Platform（Apple）の trx_diamond の行
        $free = DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', 'free_diamond')->value('free_amount');
        $paid = DB::connection($this->connection)->table('trx_diamond')
            ->where('sys_player_id', $this->sysPlayerId)->where('platform', 'Apple')->value('paid_amount');

        return ['free' => (int) $free, 'paid' => (int) $paid];
    }

    /**
     * X-Platform（Apple）の trx_diamond_balance の current_amount の合計
     */
    private function balanceTotal(): int
    {
        return (int) DB::connection($this->connection)->table('trx_diamond_balance')
            ->where('sys_player_id', $this->sysPlayerId)->where('platform', 'Apple')->sum('current_amount');
    }

    private function itemAmount(string $mstItemId): int
    {
        $row = DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', $mstItemId)->first();

        return $row === null ? 0 : (int) $row->free_amount + (int) $row->paid_amount;
    }

    private function createPlayerData(): void
    {
        $db = DB::connection($this->connection);

        // 無償ダイヤは trx_item の free_diamond、有償は X-Platform（Apple）の trx_diamond と
        // 同じ合計の trx_diamond_balance（消費は balance を古い順に引くため、両方を 1 組で入れる）
        $this->seedFreeDiamond($this->sysPlayerId, 100);
        $this->seedPaidDiamond($this->sysPlayerId, 'Apple', 50);
        $db->table('trx_item')->insert([
            'sys_player_id' => $this->sysPlayerId, 'mst_item_id' => self::TICKET_ID, 'free_amount' => 3, 'paid_amount' => 0, 'is_delete' => false,
        ]);
        $db->table('trx_wallet')->insert([
            'sys_player_id' => $this->sysPlayerId, 'mst_item_id' => self::COIN_ID, 'free_amount' => 500, 'paid_amount' => 0, 'is_delete' => false,
        ]);
        $db->table('trx_wallet_balance')->insert([
            'sys_player_id' => $this->sysPlayerId, 'mst_item_id' => self::COIN_ID, 'is_paid' => false,
            'current_amount' => 500, 'initial_amount' => 500, 'expire_at' => null, 'is_delete' => false,
        ]);
    }

    private function createMaster(): void
    {
        DB::connection('mst')->table('mst_item')->insert([
            ['id' => self::COIN_ID, 'type' => 'currency', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => self::TICKET_ID, 'type' => 'ticket', 'effect' => 'none', 'value' => 0, 'is_wallet' => false],
            ['id' => self::REWARD_ID, 'type' => 'material', 'effect' => 'none', 'value' => 0, 'is_wallet' => false],
        ]);

        DB::connection('mst')->table('mst_trade')->insert([
            ['id' => 'trade_test_general', 'start_at' => '2026-01-01 00:00:00', 'end_at' => null, 'sort_order' => 1],
            ['id' => 'trade_test_closed', 'start_at' => '2026-01-01 00:00:00', 'end_at' => '2026-09-30 23:59:59', 'sort_order' => 0],
        ]);

        $product = fn (string $id, string $costType, ?string $costMstId, int $costAmount, int $sortOrder, array $overrides = []) => [
            'id' => $id,
            'mst_trade_id' => 'trade_test_general',
            'cost_type' => $costType,
            'cost_mst_id' => $costMstId,
            'cost_amount' => $costAmount,
            'trade_limit' => null,
            'reset_type' => 'none',
            'start_at' => '2026-01-01 00:00:00',
            'end_at' => null,
            'sort_order' => $sortOrder,
            ...$overrides,
        ];
        DB::connection('mst')->table('mst_trade_product')->insert([
            $product('product_by_diamond', 'diamond', null, 30, 1),
            $product('product_by_paid_diamond', 'paid_diamond', null, 80, 2),
            $product('product_by_coin', 'wallet', self::COIN_ID, 200, 3),
            $product('product_by_item', 'item', self::TICKET_ID, 5, 4),
            $product('product_daily_limited', 'item', self::TICKET_ID, 1, 5, ['trade_limit' => 1, 'reset_type' => 'daily']),
            $product('product_expired', 'diamond', null, 1, 6, ['end_at' => '2026-10-15 11:59:59']),
            $product('product_in_closed_trade', 'diamond', null, 1, 7, ['mst_trade_id' => 'trade_test_closed']),
        ]);

        DB::connection('mst')->table('mst_trade_product_content')->insert(array_map(
            fn (string $productId) => [
                'mst_trade_product_id' => $productId,
                'content_type' => 'item',
                'content_mst_id' => self::REWARD_ID,
                'content_quantity' => $productId === 'product_by_diamond' ? 2 : 1,
                'sort_order' => 1,
            ],
            self::PRODUCT_IDS,
        ));
    }

    private function cleanUpMaster(): void
    {
        DB::connection('mst')->table('mst_trade_product_content')->whereIn('mst_trade_product_id', self::PRODUCT_IDS)->delete();
        DB::connection('mst')->table('mst_trade_product')->whereIn('id', self::PRODUCT_IDS)->delete();
        DB::connection('mst')->table('mst_trade')->whereIn('id', self::TRADE_IDS)->delete();
        DB::connection('mst')->table('mst_item')->whereIn('id', [self::COIN_ID, self::TICKET_ID, self::REWARD_ID])->delete();
    }
}
