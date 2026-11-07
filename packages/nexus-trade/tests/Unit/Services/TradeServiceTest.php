<?php

namespace NexusTrade\Tests\Unit\Services;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use Nexus\Core\ResourceDelivery\Services\ResourceDeliveryService;
use Nexus\Core\Utilities\ClockUtility;
use NexusTrade\Constants\TradeResetType;
use NexusTrade\Contracts\TradeCostPayerInterface;
use NexusTrade\Contracts\TradeMasterRepositoryInterface;
use NexusTrade\DataTransferObjects\Trade;
use NexusTrade\DataTransferObjects\TradeProduct;
use NexusTrade\DataTransferObjects\TradeProductContent;
use NexusTrade\DataTransferObjects\TradeProductCount;
use NexusTrade\Exceptions\TradeException;
use NexusTrade\Repositories\TradeLogRepositoryInterface;
use NexusTrade\Repositories\TradeProductCountRepositoryInterface;
use NexusTrade\Services\TradeService;
use NexusTrade\ValueObjects\TradeProductStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TradeServiceのユニットテスト
 *
 * 交換期間・交換回数の上限・周期ごとの数え直し・コストと手に入るものの個数倍を固定する。
 * ゲーム内日付は 04:00 で切り替える。
 */
class TradeServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const SYS_PLAYER_ID = 100;

    private FakeTradeMasterRepository $masterRepository;

    private FakeTradeProductCountRepository $tradeProductCountRepository;

    private FakeTradeLogRepository $tradeLogRepository;

    private FakeTradeCostPayer $costPayer;

    /** @var ResourceDeliveryService&MockInterface */
    private ResourceDeliveryService $deliveryService;

    private TradeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setDayStartTime('04:00:00');
        ClockUtility::setNow('2026-10-15 12:00:00');

        $this->masterRepository = new FakeTradeMasterRepository;
        $this->tradeProductCountRepository = new FakeTradeProductCountRepository;
        $this->tradeLogRepository = new FakeTradeLogRepository;
        $this->costPayer = new FakeTradeCostPayer;
        $this->deliveryService = Mockery::mock(ResourceDeliveryService::class);

        $this->service = new TradeService(
            $this->masterRepository,
            $this->tradeProductCountRepository,
            $this->tradeLogRepository,
            $this->costPayer,
            $this->deliveryService,
        );

        $this->masterRepository->trades = [
            new Trade('trade_general', '2026-01-01 00:00:00', null, 1),
        ];
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();
        ClockUtility::setDayStartTime('00:00:00');

        parent::tearDown();
    }

    #[Test]
    public function 開催中の交換所にある交換期間内の商品だけを表示順に返す(): void
    {
        $this->masterRepository->trades[] = new Trade('trade_event', '2026-10-01 00:00:00', '2026-10-31 23:59:59', 0);
        $this->masterRepository->trades[] = new Trade('trade_closed', '2026-01-01 00:00:00', '2026-09-30 23:59:59', 2);
        $this->masterRepository->products = [
            $this->product('general_b', sortOrder: 2),
            $this->product('general_a', sortOrder: 1),
            $this->product('general_expired', endAt: '2026-10-14 23:59:59'),
            $this->product('general_future', startAt: '2026-10-16 00:00:00'),
            $this->product('event_a', mstTradeId: 'trade_event'),
            $this->product('closed_a', mstTradeId: 'trade_closed'),
        ];

        $ids = array_map(
            fn (TradeProductStatus $status) => $status->getTradeProduct()->getId(),
            $this->service->findStatuses(self::SYS_PLAYER_ID),
        );

        // 交換所の表示順（event=0 → general=1）、その中で商品の表示順
        $this->assertSame(['event_a', 'general_a', 'general_b'], $ids);
    }

    #[Test]
    public function 一覧に今の周期での交換回数と残り回数が載る(): void
    {
        $this->masterRepository->products = [$this->product('daily', tradeLimit: 3, resetType: TradeResetType::DAILY)];
        $this->tradeProductCountRepository->counts['daily'] = new TradeProductCount(self::SYS_PLAYER_ID, 'daily', 2, '2026-10-15 05:00:00');

        $status = $this->service->findStatuses(self::SYS_PLAYER_ID)[0];

        $this->assertSame(2, $status->getTradedCount());
        $this->assertSame(1, $status->getRemainingCount());
        $this->assertFalse($status->isSoldOut());
    }

    #[Test]
    public function 交換するとコストを払い手に入るものを個数分配って回数とログを残す(): void
    {
        $this->masterRepository->products = [$this->product('potion_set', costAmount: 50, tradeLimit: 5)];
        $this->masterRepository->contents['potion_set'] = [
            new TradeProductContent('potion_set', 'item', 'potion', 3, 1),
            new TradeProductContent('potion_set', 'item', 'elixir', 1, 2),
        ];

        $this->deliveryService->shouldReceive('addContents')
            ->once()
            ->withArgs(fn (array $contents) => array_map(
                fn (ResourceDeliveryContent $content) => [$content->getId(), $content->getAmount()],
                $contents,
            ) === [['potion', 6], ['elixir', 2]]);
        $this->deliveryService->shouldReceive('deliver')->once()->with(self::SYS_PLAYER_ID);

        $status = $this->service->trade(self::SYS_PLAYER_ID, 'potion_set', 2);

        $this->assertSame([[self::SYS_PLAYER_ID, 'item', 'gold', 100]], $this->costPayer->payments);
        $this->assertSame(2, $status->getTradedCount());
        $this->assertSame(3, $status->getRemainingCount());
        $this->assertSame(2, $this->tradeProductCountRepository->counts['potion_set']->getTradeCount());
        $this->assertSame('2026-10-15 12:00:00', $this->tradeProductCountRepository->counts['potion_set']->getLastTradedAt());
        $this->assertSame([[self::SYS_PLAYER_ID, 'potion_set', 2, 'item', 'gold', 100]], $this->tradeLogRepository->logs);
    }

    #[Test]
    public function 上限を超える個数は交換できずコストも払わない(): void
    {
        $this->masterRepository->products = [$this->product('limited', tradeLimit: 3)];
        $this->tradeProductCountRepository->counts['limited'] = new TradeProductCount(self::SYS_PLAYER_ID, 'limited', 2, '2026-10-15 05:00:00');

        try {
            $this->service->trade(self::SYS_PLAYER_ID, 'limited', 2);
            $this->fail('例外が出るべき');
        } catch (TradeException $e) {
            $this->assertSame(TradeException::CODE_TRADE_LIMIT_EXCEEDED, $e->getErrorCode());
        }

        $this->assertSame([], $this->costPayer->payments);
        $this->assertSame(2, $this->tradeProductCountRepository->counts['limited']->getTradeCount());
    }

    #[Test]
    public function 上限が無ければ何回でも交換できる(): void
    {
        $this->masterRepository->products = [$this->product('unlimited', tradeLimit: null)];
        $this->tradeProductCountRepository->counts['unlimited'] = new TradeProductCount(self::SYS_PLAYER_ID, 'unlimited', 999, '2026-10-15 05:00:00');
        $this->deliveryService->shouldNotReceive('addContents');

        $status = $this->service->trade(self::SYS_PLAYER_ID, 'unlimited', 10);

        $this->assertSame(1009, $status->getTradedCount());
        $this->assertNull($status->getRemainingCount());
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function periodProvider(): array
    {
        // 現在は 2026-10-15(木) 12:00。ゲーム内日付の境界は 04:00
        return [
            '日: 同じゲーム内日付' => [TradeResetType::DAILY, '2026-10-15 04:00:00', 2],
            '日: 前日' => [TradeResetType::DAILY, '2026-10-15 03:59:59', 0],
            '週: 同じ週の月曜' => [TradeResetType::WEEKLY, '2026-10-12 04:00:00', 2],
            '週: 前の週（月曜の境界前は前の週）' => [TradeResetType::WEEKLY, '2026-10-12 03:59:59', 0],
            '週: 去年の同じ週番号' => [TradeResetType::WEEKLY, '2025-10-16 12:00:00', 0],
            '月: 同じ月の1日' => [TradeResetType::MONTHLY, '2026-10-01 04:00:00', 2],
            '月: 前の月（1日の境界前は前の月）' => [TradeResetType::MONTHLY, '2026-10-01 03:59:59', 0],
            '月: 去年の同じ月' => [TradeResetType::MONTHLY, '2025-10-15 12:00:00', 0],
            'なし: 去年でも通算' => [TradeResetType::NONE, '2025-01-01 00:00:00', 2],
        ];
    }

    #[Test]
    #[DataProvider('periodProvider')]
    public function 周期が変わると交換回数を0に戻す(string $resetType, string $lastTradedAt, int $expected): void
    {
        $this->masterRepository->products = [$this->product('periodic', tradeLimit: 5, resetType: $resetType)];
        $this->tradeProductCountRepository->counts['periodic'] = new TradeProductCount(self::SYS_PLAYER_ID, 'periodic', 2, $lastTradedAt);

        $this->assertSame($expected, $this->service->findStatuses(self::SYS_PLAYER_ID)[0]->getTradedCount());
    }

    #[Test]
    public function 年をまたぐ週は_is_o週で同じ週とみなす(): void
    {
        // 2025-12-29(月) と 2026-01-01(木) は同じ ISO 週（2026年第1週）
        ClockUtility::setNow('2026-01-01 12:00:00');
        $this->masterRepository->products = [$this->product('weekly', tradeLimit: 5, resetType: TradeResetType::WEEKLY)];
        $this->tradeProductCountRepository->counts['weekly'] = new TradeProductCount(self::SYS_PLAYER_ID, 'weekly', 2, '2025-12-29 12:00:00');

        $this->assertSame(2, $this->service->findStatuses(self::SYS_PLAYER_ID)[0]->getTradedCount());
    }

    #[Test]
    public function 周期が変わったら上限まで交換し直せる(): void
    {
        $this->masterRepository->products = [$this->product('daily', tradeLimit: 1, resetType: TradeResetType::DAILY)];
        $this->tradeProductCountRepository->counts['daily'] = new TradeProductCount(self::SYS_PLAYER_ID, 'daily', 1, '2026-10-14 12:00:00');
        $this->deliveryService->shouldNotReceive('addContents');

        $status = $this->service->trade(self::SYS_PLAYER_ID, 'daily', 1);

        $this->assertSame(1, $status->getTradedCount());
        $this->assertTrue($status->isSoldOut());
    }

    #[Test]
    public function 存在しない商品は交換できない(): void
    {
        $this->assertTradeException(TradeException::CODE_PRODUCT_NOT_FOUND, fn () => $this->service->trade(self::SYS_PLAYER_ID, 'missing', 1));
    }

    #[Test]
    public function 交換期間外の商品は交換できない(): void
    {
        $this->masterRepository->products = [$this->product('expired', endAt: '2026-10-15 11:59:59')];

        $this->assertTradeException(TradeException::CODE_PRODUCT_NOT_OPEN, fn () => $this->service->trade(self::SYS_PLAYER_ID, 'expired', 1));
    }

    #[Test]
    public function 終了した交換所の商品は商品の期間内でも交換できない(): void
    {
        $this->masterRepository->trades = [new Trade('trade_general', '2026-01-01 00:00:00', '2026-10-15 11:59:59', 1)];
        $this->masterRepository->products = [$this->product('item')];

        $this->assertTradeException(TradeException::CODE_PRODUCT_NOT_OPEN, fn () => $this->service->trade(self::SYS_PLAYER_ID, 'item', 1));
    }

    #[Test]
    public function 個数が0以下なら交換できない(): void
    {
        $this->masterRepository->products = [$this->product('item')];

        $this->assertTradeException(TradeException::CODE_INVALID_QUANTITY, fn () => $this->service->trade(self::SYS_PLAYER_ID, 'item', 0));
    }

    /**
     * 例外の種類（TradeException::CODE_*）まで確かめる
     *
     * @param  callable(): mixed  $callback
     */
    private function assertTradeException(string $errorCode, callable $callback): void
    {
        try {
            $callback();
            $this->fail('TradeException が出るべき');
        } catch (TradeException $e) {
            $this->assertSame($errorCode, $e->getErrorCode());
        }
    }

    private function product(
        string $id,
        string $mstTradeId = 'trade_general',
        int $costAmount = 10,
        ?int $tradeLimit = null,
        string $resetType = TradeResetType::NONE,
        string $startAt = '2026-01-01 00:00:00',
        ?string $endAt = null,
        int $sortOrder = 0,
    ): TradeProduct {
        return new TradeProduct($id, $mstTradeId, 'item', 'gold', $costAmount, $tradeLimit, $resetType, $startAt, $endAt, $sortOrder);
    }
}

class FakeTradeMasterRepository implements TradeMasterRepositoryInterface
{
    /** @var array<int, Trade> */
    public array $trades = [];

    /** @var array<int, TradeProduct> */
    public array $products = [];

    /** @var array<string, array<int, TradeProductContent>> */
    public array $contents = [];

    public function selectAllTrades(): array
    {
        return $this->trades;
    }

    public function selectTradeById(string $mstTradeId): ?Trade
    {
        foreach ($this->trades as $trade) {
            if ($trade->getId() === $mstTradeId) {
                return $trade;
            }
        }

        return null;
    }

    public function selectAllProducts(): array
    {
        return $this->products;
    }

    public function selectProductById(string $mstTradeProductId): ?TradeProduct
    {
        foreach ($this->products as $product) {
            if ($product->getId() === $mstTradeProductId) {
                return $product;
            }
        }

        return null;
    }

    public function selectContentsByProductId(string $mstTradeProductId): array
    {
        return $this->contents[$mstTradeProductId] ?? [];
    }
}

class FakeTradeProductCountRepository implements TradeProductCountRepositoryInterface
{
    /** @var array<string, TradeProductCount> */
    public array $counts = [];

    public function selectByPlayerId(int $sysPlayerId): array
    {
        return array_values($this->counts);
    }

    public function selectByProductId(int $sysPlayerId, string $mstTradeProductId): ?TradeProductCount
    {
        return $this->counts[$mstTradeProductId] ?? null;
    }

    public function save(TradeProductCount $tradeProductCount): void
    {
        $this->counts[$tradeProductCount->getMstTradeProductId()] = $tradeProductCount;
    }
}

class FakeTradeLogRepository implements TradeLogRepositoryInterface
{
    /** @var list<array{int, string, int, string, ?string, int}> */
    public array $logs = [];

    public function insert(
        int $sysPlayerId,
        string $mstTradeProductId,
        int $quantity,
        string $costType,
        ?string $costMstId,
        int $costAmount,
    ): void {
        $this->logs[] = [$sysPlayerId, $mstTradeProductId, $quantity, $costType, $costMstId, $costAmount];
    }
}

class FakeTradeCostPayer implements TradeCostPayerInterface
{
    /** @var list<array{int, string, ?string, int}> */
    public array $payments = [];

    public function pay(int $sysPlayerId, string $costType, ?string $costMstId, int $amount): void
    {
        $this->payments[] = [$sysPlayerId, $costType, $costMstId, $amount];
    }
}
