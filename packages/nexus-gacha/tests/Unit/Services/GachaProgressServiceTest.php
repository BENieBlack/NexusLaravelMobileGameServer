<?php

namespace NexusGacha\Tests\Unit\Services;

use Nexus\Core\Utilities\ClockUtility;
use NexusGacha\DataTransferObjects\GachaProgress;
use NexusGacha\Repositories\GachaProgressRepositoryInterface;
use NexusGacha\Services\GachaProgressService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * GachaProgressService のユニットテスト
 *
 * 日次リセットは daily_reset_at の日付部分と「今日」を比べて決める。
 * daily_reset_at はDB側がnullableなので、未リセット（null）の扱いも確認する。
 */
class GachaProgressServiceTest extends TestCase
{
    private FakeGachaProgressRepository $repository;

    private GachaProgressService $service;

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 09:00:00');
        $this->repository = new FakeGachaProgressRepository;
        $this->service = new GachaProgressService($this->repository);
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function 進行状況が無ければ作成する(): void
    {
        $progress = $this->service->findOrInsertProgress(100, 'gacha_001');

        $this->assertSame(100, $progress->getSysPlayerId());
        $this->assertSame(1, $progress->getCurrentStep());
        $this->assertSame(0, $progress->getDailyDrawCount());
        $this->assertSame('2026-03-15 09:00:00', $progress->getDailyResetAt());
        $this->assertCount(1, $this->repository->inserted);
    }

    #[Test]
    public function 既存の進行状況はそのまま返す(): void
    {
        $this->repository->stored = $this->makeProgress(dailyDrawCount: 3);

        $progress = $this->service->findOrInsertProgress(100, 'gacha_001');

        $this->assertSame(3, $progress->getDailyDrawCount());
        $this->assertSame([], $this->repository->inserted, '既存があればINSERTしない');
    }

    #[Test]
    public function 前日にリセットしていれば日次カウントを戻す(): void
    {
        $progress = $this->makeProgress(dailyDrawCount: 7, dailyResetAt: '2026-03-14 23:59:59');

        $reset = $this->service->checkAndResetDaily($progress);

        $this->assertSame(0, $reset->getDailyDrawCount());
        $this->assertSame('2026-03-15 09:00:00', $reset->getDailyResetAt());
    }

    #[Test]
    public function 同じ日にリセット済みなら何もしない(): void
    {
        $progress = $this->makeProgress(dailyDrawCount: 7, dailyResetAt: '2026-03-15 00:00:00');

        $reset = $this->service->checkAndResetDaily($progress);

        $this->assertSame(7, $reset->getDailyDrawCount());
        $this->assertSame('2026-03-15 00:00:00', $reset->getDailyResetAt());
    }

    #[Test]
    public function 一度もリセットしていなければリセットする(): void
    {
        // daily_reset_at はDBでnullable。null のまま渡ってきても落ちずにリセットする
        $progress = $this->makeProgress(dailyDrawCount: 7, dailyResetAt: null);

        $reset = $this->service->checkAndResetDaily($progress);

        $this->assertSame(0, $reset->getDailyDrawCount());
        $this->assertSame('2026-03-15 09:00:00', $reset->getDailyResetAt());
    }

    #[Test]
    public function 実行後にカウントを加算して保存する(): void
    {
        $progress = $this->makeProgress(dailyDrawCount: 2, totalDrawCount: 20);

        $this->service->updateProgress($progress, drawCount: 10);

        $this->assertSame(3, $progress->getDailyDrawCount(), '日次は実行回数ではなく実行1回で+1');
        $this->assertSame(30, $progress->getTotalDrawCount(), '累計は引いた回数分だけ増える');
        $this->assertSame(1, $progress->getCurrentStep(), 'ステップ指定が無ければ据え置き');
        $this->assertSame([$progress], $this->repository->persisted);
    }

    #[Test]
    public function 次のステップが指定されていれば進める(): void
    {
        $progress = $this->makeProgress();

        $this->service->updateProgress($progress, drawCount: 1, nextStep: 5);

        $this->assertSame(5, $progress->getCurrentStep());
    }

    private function makeProgress(
        int $dailyDrawCount = 0,
        ?string $dailyResetAt = '2026-03-15 00:00:00',
        int $totalDrawCount = 0,
    ): GachaProgress {
        return new GachaProgress(
            sysPlayerId: 100,
            mstGachaId: 'gacha_001',
            currentStep: 1,
            dailyDrawCount: $dailyDrawCount,
            dailyResetAt: $dailyResetAt,
            totalDrawCount: $totalDrawCount,
            totalResetAt: '2026-03-01 00:00:00',
        );
    }
}

/**
 * メモリ上で完結するGachaProgressRepositoryInterface実装
 */
class FakeGachaProgressRepository implements GachaProgressRepositoryInterface
{
    public ?GachaProgress $stored = null;

    /** @var list<GachaProgress> */
    public array $inserted = [];

    /** @var list<GachaProgress> */
    public array $persisted = [];

    public function selectByPlayerAndGacha(int $sysPlayerId, string $mstGachaId): ?GachaProgress
    {
        return $this->stored;
    }

    public function persist(GachaProgress $gachaProgress): void
    {
        $this->persisted[] = $gachaProgress;
    }

    public function insert(GachaProgress $gachaProgress): GachaProgress
    {
        $this->inserted[] = $gachaProgress;

        return $gachaProgress;
    }
}
