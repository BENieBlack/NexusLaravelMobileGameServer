<?php

namespace Tests\Unit\Repositories\Trx;

use App\Models\Trx\TrxEquipment;
use App\Persistence\ApiSession;
use App\Repositories\Trx\TrxEquipmentRepository;
use Nexus\Core\Utilities\ClockUtility;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class TrxEquipmentRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected TrxEquipmentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // ApiSessionを初期化（テスト用のプレイヤーID=1を設定）
        ClockUtility::initialize();
        ApiSession::setSysPlayerId(1);

        $this->repository = new TrxEquipmentRepository;
    }

    /**
     * Test setModel queues new equipment for INSERT
     */
    public function test_set_model_queues_new_equipment_for_insert(): void
    {
        // Arrange
        $trxEquipment = new TrxEquipment([
            'sys_player_id' => 1,
            'mst_equipment_id' => 'equipment_001',
            'grade' => 1,
            'level' => 1,
        ]);
        $trxEquipment->exists = false;

        // Act
        $this->repository->setModel($trxEquipment);
        $queuedModels = array_values($this->repository->getQueuedModels());

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals(1, $queuedModels[0]->sys_player_id);
        $this->assertEquals('equipment_001', $queuedModels[0]->mst_equipment_id);
        $this->assertEquals(1, $queuedModels[0]->level);
        $this->assertFalse($queuedModels[0]->exists);
    }

    /**
     * Test setModel queues existing equipment for UPDATE
     */
    public function test_set_model_queues_existing_equipment_for_update(): void
    {
        // Arrange
        $trxEquipment = TrxEquipment::create([
            'sys_player_id' => 1,
            'mst_equipment_id' => 'equipment_002',
            'grade' => 1,
            'level' => 1,
        ]);

        // Modify the equipment
        $trxEquipment->level = 5;

        // Act
        $this->repository->setModel($trxEquipment);
        $queuedModels = array_values($this->repository->getQueuedModels());

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals(5, $queuedModels[0]->level);
        $this->assertTrue($queuedModels[0]->exists);
    }

    /**
     * Test queryOrMemory returns equipment for player
     */
    public function test_query_or_memory_returns_equipment_for_player(): void
    {
        // Arrange
        $sysPlayerId = 1;
        TrxEquipment::create([
            'sys_player_id' => $sysPlayerId,
            'mst_equipment_id' => 'equipment_001',
            'grade' => 1,
            'level' => 1,
        ]);

        TrxEquipment::create([
            'sys_player_id' => $sysPlayerId,
            'mst_equipment_id' => 'equipment_002',
            'grade' => 1,
            'level' => 3,
        ]);

        TrxEquipment::create([
            'sys_player_id' => 2, // Different player
            'mst_equipment_id' => 'equipment_003',
            'grade' => 1,
            'level' => 1,
        ]);

        // Act
        $result = $this->repository->queryOrMemory($sysPlayerId, TrxEquipment::class);

        // Assert
        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($equipment) => $equipment->sys_player_id === $sysPlayerId));
    }

    /**
     * Test selectById returns correct equipment from memory
     */
    public function test_select_by_id_returns_equipment_from_memory(): void
    {
        // Arrange
        $trxEquipment = TrxEquipment::create([
            'sys_player_id' => 1,
            'mst_equipment_id' => 'equipment_004',
            'grade' => 1,
            'level' => 10,
        ]);

        // Load into memory via queryOrMemory
        $this->repository->queryOrMemory(1, TrxEquipment::class);

        // Act
        $result = $this->repository->selectById($trxEquipment->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($trxEquipment->id, $result->id);
        $this->assertEquals('equipment_004', $result->mst_equipment_id);
        $this->assertEquals(10, $result->level);
    }

    /**
     * Test selectById returns null for non-existent equipment
     */
    public function test_select_by_id_returns_null_for_non_existent_equipment(): void
    {
        // Act
        $result = $this->repository->selectById(99999);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test createEquipment creates new equipment and queues it
     */
    public function test_create_equipment_creates_new_equipment_and_queues_it(): void
    {
        // Act
        $trxEquipment = $this->repository->insertEquipment(
            mstEquipmentId: 'equipment_005',
            level: 5
        );

        $queuedModels = array_values($this->repository->getQueuedModels());

        // Assert
        $this->assertNotNull($trxEquipment);
        $this->assertEquals(1, $trxEquipment->sys_player_id);
        $this->assertEquals('equipment_005', $trxEquipment->mst_equipment_id);
        $this->assertEquals(5, $trxEquipment->level);

        $this->assertCount(1, $queuedModels);
        $this->assertEquals('equipment_005', $queuedModels[0]->mst_equipment_id);
    }

    /**
     * Test createEquipment with default level value
     */
    public function test_create_equipment_with_default_level(): void
    {
        // Act
        $trxEquipment = $this->repository->insertEquipment(
            mstEquipmentId: 'equipment_006'
        );

        // Assert
        $this->assertEquals(1, $trxEquipment->level); // Default level
    }

    /**
     * Test createEquipment uses ApiSession for player ID
     */
    public function test_create_equipment_uses_api_session_for_player_id(): void
    {
        // Arrange
        ApiSession::setSysPlayerId(42);
        $repository = new TrxEquipmentRepository;

        // Act
        $trxEquipment = $repository->insertEquipment(
            mstEquipmentId: 'equipment_007',
            level: 3
        );

        // Assert
        $this->assertEquals(42, $trxEquipment->sys_player_id);
    }

    /**
     * Test clearQueue removes all queued models
     */
    public function test_clear_queue_removes_all_queued_models(): void
    {
        // Arrange
        $trxEquipment1 = new TrxEquipment([
            'sys_player_id' => 1,
            'mst_equipment_id' => 'equipment_010',
            'grade' => 1,
            'level' => 1,
        ]);
        $trxEquipment1->exists = false;

        $trxEquipment2 = new TrxEquipment([
            'sys_player_id' => 1,
            'mst_equipment_id' => 'equipment_011',
            'grade' => 1,
            'level' => 1,
        ]);
        $trxEquipment2->exists = false;

        $this->repository->setModel($trxEquipment1);
        $this->repository->setModel($trxEquipment2);

        // Act
        $this->repository->clearQueue();
        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(0, $queuedModels);
    }

    /**
     * Test multiple equipment can be queued
     */
    public function test_multiple_equipment_can_be_queued(): void
    {
        // Arrange & Act
        for ($i = 1; $i <= 5; $i++) {
            $this->repository->insertEquipment(
                mstEquipmentId: "equipment_batch_{$i}",
                level: $i
            );
        }

        $queuedModels = array_values($this->repository->getQueuedModels());

        // Assert
        $this->assertCount(5, $queuedModels);
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals('equipment_batch_'.($i + 1), $queuedModels[$i]->mst_equipment_id);
            $this->assertEquals($i + 1, $queuedModels[$i]->level);
        }
    }

    /**
     * Test memory cache consistency
     */
    public function test_memory_cache_consistency(): void
    {
        // Arrange
        $trxEquipment = TrxEquipment::create([
            'sys_player_id' => 1,
            'mst_equipment_id' => 'equipment_012',
            'grade' => 1,
            'level' => 1,
        ]);

        // Act - Load into memory
        $equipments = $this->repository->queryOrMemory(1, TrxEquipment::class);
        $equipmentFromMemory = $this->repository->selectById($trxEquipment->id);

        // Assert
        $this->assertCount(1, $equipments);
        $this->assertNotNull($equipmentFromMemory);
        $this->assertEquals($trxEquipment->id, $equipmentFromMemory->id);
    }

    /**
     * Test createEquipment sets timestamps
     */
    public function test_create_equipment_sets_timestamps(): void
    {
        // Act
        $trxEquipment = $this->repository->insertEquipment(
            mstEquipmentId: 'equipment_013',
            level: 2
        );

        // Assert
        $this->assertNotNull($trxEquipment->created_at);
        $this->assertNotNull($trxEquipment->updated_at);
        $this->assertEquals(
            $trxEquipment->created_at->format('Y-m-d H:i:s'),
            $trxEquipment->updated_at->format('Y-m-d H:i:s')
        );
    }

    /**
     * キューに積むだけでなく、フラッシュ後にDBへ反映されることを検証する
     */
    public function test_queued_insert_is_written_to_database_after_flush(): void
    {
        // Arrange
        $trxEquipment = new TrxEquipment([
            'sys_player_id' => 1,
            'mst_equipment_id' => 'equipment_flush_001',
            'grade' => 2,
            'level' => 3,
        ]);
        $trxEquipment->exists = false;

        // Act
        $this->repository->setModel($trxEquipment);
        $this->flushQueue();

        // Assert
        $this->assertDatabaseHas('trx_equipment', [
            'sys_player_id' => 1,
            'mst_equipment_id' => 'equipment_flush_001',
            'grade' => 2,
            'level' => 3,
        ], 'trx1');
    }

    /**
     * 更新もフラッシュ後にDBへ反映されることを検証する
     */
    public function test_queued_update_is_written_to_database_after_flush(): void
    {
        // Arrange
        $trxEquipment = new TrxEquipment([
            'sys_player_id' => 1,
            'mst_equipment_id' => 'equipment_flush_002',
            'grade' => 1,
            'level' => 1,
        ]);
        $trxEquipment->exists = false;
        $this->repository->setModel($trxEquipment);
        $this->flushQueue();

        // Act
        $trxEquipment->level = 10;
        $this->repository->setModel($trxEquipment);
        $this->flushQueue();

        // Assert
        $this->assertDatabaseHas('trx_equipment', [
            'id' => $trxEquipment->id,
            'level' => 10,
        ], 'trx1');
    }
}
