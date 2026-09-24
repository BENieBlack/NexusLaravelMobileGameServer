<?php

namespace Tests\Unit\Repositories\Trx;

use App\Persistence\ApiSession;
use App\Repositories\Trx\EquipmentRepositoryAdapter;
use App\Repositories\Trx\TrxEquipmentRepository;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * EquipmentRepositoryAdapter のテスト
 *
 * nexus-resource-deliveryから渡されたプレイヤーIDで
 * 装備がキューに積まれることを検証する。
 */
class EquipmentRepositoryAdapterTest extends TestCase
{
    private TrxEquipmentRepository $trxEquipmentRepository;

    private EquipmentRepositoryAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::initialize();
        // セッションの本人は1。配送先とは別人にして取り違えを検出する
        ApiSession::setSysPlayerId(1);

        $this->trxEquipmentRepository = new TrxEquipmentRepository;
        $this->adapter = new EquipmentRepositoryAdapter($this->trxEquipmentRepository);
    }

    #[Test]
    public function test_queues_equipment_for_the_given_player(): void
    {
        // セッションの本人（1）ではなく、引数で渡した777に付与されること
        $this->adapter->insertEquipment(777, 'equipment_sword_001');

        $queued = array_values($this->trxEquipmentRepository->getQueuedModels());

        $this->assertCount(1, $queued);
        $this->assertSame(777, $queued[0]->getSysPlayerId());
        $this->assertSame('equipment_sword_001', $queued[0]->getMstEquipmentId());
        $this->assertFalse($queued[0]->exists, 'INSERTとして扱われること');
    }

    #[Test]
    public function test_uses_default_level_and_grade_when_not_specified(): void
    {
        $this->adapter->insertEquipment(777, 'equipment_sword_001');

        $queued = array_values($this->trxEquipmentRepository->getQueuedModels());

        $this->assertSame(1, $queued[0]->getLevel());
        $this->assertSame(1, $queued[0]->getGrade());
    }

    #[Test]
    public function test_uses_specified_level_and_grade(): void
    {
        $this->adapter->insertEquipment(777, 'equipment_sword_001', level: 20, grade: 3);

        $queued = array_values($this->trxEquipmentRepository->getQueuedModels());

        $this->assertSame(20, $queued[0]->getLevel());
        $this->assertSame(3, $queued[0]->getGrade());
    }

    #[Test]
    public function test_queues_every_call_separately(): void
    {
        $this->adapter->insertEquipment(777, 'equipment_sword_001');
        $this->adapter->insertEquipment(777, 'equipment_sword_001');

        // 同じマスターIDでも別個体なので、まとめられず2件積まれること
        $this->assertCount(2, $this->trxEquipmentRepository->getQueuedModels());
    }
}
