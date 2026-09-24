<?php

namespace Tests\Unit\Repositories\Trx;

use App\Persistence\ApiSession;
use App\Repositories\Trx\TrxUnitRepository;
use App\Repositories\Trx\UnitRepositoryAdapter;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UnitRepositoryAdapter のテスト
 *
 * nexus-resource-deliveryから渡されたプレイヤーIDで
 * ユニットがキューに積まれることを検証する。
 */
class UnitRepositoryAdapterTest extends TestCase
{
    private TrxUnitRepository $trxUnitRepository;

    private UnitRepositoryAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::initialize();
        // セッションの本人は1。配送先とは別人にして取り違えを検出する
        ApiSession::setSysPlayerId(1);

        $this->trxUnitRepository = new TrxUnitRepository;
        $this->adapter = new UnitRepositoryAdapter($this->trxUnitRepository);
    }

    #[Test]
    public function test_queues_unit_for_the_given_player(): void
    {
        // セッションの本人（1）ではなく、引数で渡した777に付与されること
        $this->adapter->insertUnit(777, 'unit_knight_001');

        $queued = array_values($this->trxUnitRepository->getQueuedModels());

        $this->assertCount(1, $queued);
        $this->assertSame(777, $queued[0]->getSysPlayerId());
        $this->assertSame('unit_knight_001', $queued[0]->getMstUnitId());
        $this->assertFalse($queued[0]->exists, 'INSERTとして扱われること');
    }

    #[Test]
    public function test_uses_default_grade_and_level_when_not_specified(): void
    {
        $this->adapter->insertUnit(777, 'unit_knight_001');

        $queued = array_values($this->trxUnitRepository->getQueuedModels());

        $this->assertSame(1, $queued[0]->getGrade());
        $this->assertSame(1, $queued[0]->getLevel());
        $this->assertSame(0, $queued[0]->getLevelExp());
    }

    #[Test]
    public function test_uses_specified_grade_and_level(): void
    {
        $this->adapter->insertUnit(777, 'unit_knight_001', grade: 3, level: 20);

        $queued = array_values($this->trxUnitRepository->getQueuedModels());

        $this->assertSame(3, $queued[0]->getGrade());
        $this->assertSame(20, $queued[0]->getLevel());
    }

    #[Test]
    public function test_queues_every_call_separately(): void
    {
        $this->adapter->insertUnit(777, 'unit_knight_001');
        $this->adapter->insertUnit(777, 'unit_knight_001');

        // 同じマスターIDでも別個体なので、まとめられず2件積まれること
        $this->assertCount(2, $this->trxUnitRepository->getQueuedModels());
    }
}
