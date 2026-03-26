<?php

namespace Tests\Unit\Repositories\Trx;

use App\Models\Trx\TrxUnit;
use App\Repositories\Trx\TrxUnitRepository;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class TrxUnitRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected TrxUnitRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TrxUnitRepository();
    }

    /**
     * Test setModel queues new unit for INSERT
     */
    public function test_set_model_queues_new_unit_for_insert(): void
    {
        // Arrange
        $trxUnit = new TrxUnit([
            'sys_player_id' => 1,
            'mst_unit_id' => 'unit_001',
            'grade' => 1,
            'level' => 1,
            'level_exp' => 0,
        ]);
        $trxUnit->exists = false;

        // Act
        $this->repository->setModel($trxUnit);
        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals(1, $queuedModels[0]->sys_player_id);
        $this->assertEquals('unit_001', $queuedModels[0]->mst_unit_id);
        $this->assertEquals(1, $queuedModels[0]->grade);
        $this->assertFalse($queuedModels[0]->exists);
    }

    /**
     * Test setModel queues existing unit for UPDATE
     */
    public function test_set_model_queues_existing_unit_for_update(): void
    {
        // Arrange
        $trxUnit = TrxUnit::create([
            'sys_player_id' => 1,
            'mst_unit_id' => 'unit_002',
            'grade' => 1,
            'level' => 1,
            'level_exp' => 0,
        ]);

        // Modify the unit
        $trxUnit->level = 5;
        $trxUnit->level_exp = 50;

        // Act
        $this->repository->setModel($trxUnit);
        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals(5, $queuedModels[0]->level);
        $this->assertEquals(50, $queuedModels[0]->level_exp);
        $this->assertTrue($queuedModels[0]->exists);
    }

    /**
     * Test queryOrMemory returns units for player
     */
    public function test_query_or_memory_returns_units_for_player(): void
    {
        // Arrange
        $sysPlayerId = 1;
        TrxUnit::create([
            'sys_player_id' => $sysPlayerId,
            'mst_unit_id' => 'unit_001',
            'grade' => 1,
            'level' => 1,
            'level_exp' => 0,
        ]);

        TrxUnit::create([
            'sys_player_id' => $sysPlayerId,
            'mst_unit_id' => 'unit_002',
            'grade' => 2,
            'level' => 3,
            'level_exp' => 25,
        ]);

        TrxUnit::create([
            'sys_player_id' => 2, // Different player
            'mst_unit_id' => 'unit_003',
            'grade' => 1,
            'level' => 1,
            'level_exp' => 0,
        ]);

        // Act
        $result = $this->repository->queryOrMemory($sysPlayerId, TrxUnit::class);

        // Assert
        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn($unit) => $unit->sys_player_id === $sysPlayerId));
    }

    /**
     * Test findById returns correct unit from memory
     */
    public function test_find_by_id_returns_unit_from_memory(): void
    {
        // Arrange
        $trxUnit = TrxUnit::create([
            'sys_player_id' => 1,
            'mst_unit_id' => 'unit_004',
            'grade' => 3,
            'level' => 10,
            'level_exp' => 75,
        ]);

        // Load into memory via queryOrMemory
        $this->repository->queryOrMemory(1, TrxUnit::class);

        // Act
        $result = $this->repository->findById($trxUnit->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($trxUnit->id, $result->id);
        $this->assertEquals('unit_004', $result->mst_unit_id);
        $this->assertEquals(3, $result->grade);
        $this->assertEquals(10, $result->level);
    }

    /**
     * Test findById returns null for non-existent unit
     */
    public function test_find_by_id_returns_null_for_non_existent_unit(): void
    {
        // Act
        $result = $this->repository->findById(99999);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test createUnit creates new unit and queues it
     */
    public function test_create_unit_creates_new_unit_and_queues_it(): void
    {
        // Act
        $trxUnit = $this->repository->createUnit(
            sysPlayerId: 1,
            mstUnitId: 'unit_005',
            grade: 2,
            level: 5
        );

        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertNotNull($trxUnit);
        $this->assertEquals(1, $trxUnit->sys_player_id);
        $this->assertEquals('unit_005', $trxUnit->mst_unit_id);
        $this->assertEquals(2, $trxUnit->grade);
        $this->assertEquals(5, $trxUnit->level);
        $this->assertEquals(0, $trxUnit->level_exp);

        $this->assertCount(1, $queuedModels);
        $this->assertEquals('unit_005', $queuedModels[0]->mst_unit_id);
    }

    /**
     * Test createUnit with default values
     */
    public function test_create_unit_with_default_values(): void
    {
        // Act
        $trxUnit = $this->repository->createUnit(
            sysPlayerId: 1,
            mstUnitId: 'unit_006'
        );

        // Assert
        $this->assertEquals(1, $trxUnit->grade); // Default
        $this->assertEquals(1, $trxUnit->level); // Default
        $this->assertEquals(0, $trxUnit->level_exp);
    }

    /**
     * Test addExp increases unit experience and level
     */
    public function test_add_exp_increases_unit_experience(): void
    {
        // Arrange
        $trxUnit = TrxUnit::create([
            'sys_player_id' => 1,
            'mst_unit_id' => 'unit_007',
            'grade' => 1,
            'level' => 1,
            'level_exp' => 0,
        ]);

        // Load into memory
        $this->repository->queryOrMemory(1, TrxUnit::class);

        // Act
        $this->repository->addExp($trxUnit->id, 50);

        // Get the updated unit from queue
        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals(50, $queuedModels[0]->level_exp);
        $this->assertEquals(1, $queuedModels[0]->level);
    }

    /**
     * Test addExp triggers level up when exp exceeds threshold
     */
    public function test_add_exp_triggers_level_up(): void
    {
        // Arrange
        $trxUnit = TrxUnit::create([
            'sys_player_id' => 1,
            'mst_unit_id' => 'unit_008',
            'grade' => 1,
            'level' => 1,
            'level_exp' => 80,
        ]);

        // Load into memory
        $this->repository->queryOrMemory(1, TrxUnit::class);

        // Act - Add enough exp to level up
        $this->repository->addExp($trxUnit->id, 150); // 80 + 150 = 230

        // Get the updated unit from queue
        $queuedModels = $this->repository->getQueuedModels();

        // Assert - Should level up twice (230 = 100 + 100 + 30)
        $this->assertCount(1, $queuedModels);
        $this->assertEquals(3, $queuedModels[0]->level); // Level 1 -> 3
        $this->assertEquals(30, $queuedModels[0]->level_exp); // 230 - 200 = 30
    }

    /**
     * Test upgradeGrade increases unit grade
     */
    public function test_upgrade_grade_increases_unit_grade(): void
    {
        // Arrange
        $trxUnit = TrxUnit::create([
            'sys_player_id' => 1,
            'mst_unit_id' => 'unit_009',
            'grade' => 1,
            'level' => 10,
            'level_exp' => 50,
        ]);

        // Load into memory
        $this->repository->queryOrMemory(1, TrxUnit::class);

        // Act
        $this->repository->upgradeGrade($trxUnit->id);

        // Get the updated unit from queue
        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals(2, $queuedModels[0]->grade);
    }

    /**
     * Test clearQueue removes all queued models
     */
    public function test_clear_queue_removes_all_queued_models(): void
    {
        // Arrange
        $trxUnit1 = new TrxUnit([
            'sys_player_id' => 1,
            'mst_unit_id' => 'unit_010',
            'grade' => 1,
            'level' => 1,
            'level_exp' => 0,
        ]);
        $trxUnit1->exists = false;

        $trxUnit2 = new TrxUnit([
            'sys_player_id' => 1,
            'mst_unit_id' => 'unit_011',
            'grade' => 1,
            'level' => 1,
            'level_exp' => 0,
        ]);
        $trxUnit2->exists = false;

        $this->repository->setModel($trxUnit1);
        $this->repository->setModel($trxUnit2);

        // Act
        $this->repository->clearQueue();
        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(0, $queuedModels);
    }

    /**
     * Test multiple units can be queued
     */
    public function test_multiple_units_can_be_queued(): void
    {
        // Arrange & Act
        for ($i = 1; $i <= 5; $i++) {
            $this->repository->createUnit(
                sysPlayerId: 1,
                mstUnitId: "unit_batch_{$i}",
                grade: 1,
                level: $i
            );
        }

        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(5, $queuedModels);
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals("unit_batch_" . ($i + 1), $queuedModels[$i]->mst_unit_id);
            $this->assertEquals($i + 1, $queuedModels[$i]->level);
        }
    }

    /**
     * Test memory cache consistency
     */
    public function test_memory_cache_consistency(): void
    {
        // Arrange
        $trxUnit = TrxUnit::create([
            'sys_player_id' => 1,
            'mst_unit_id' => 'unit_012',
            'grade' => 1,
            'level' => 1,
            'level_exp' => 0,
        ]);

        // Act - Load into memory
        $units = $this->repository->queryOrMemory(1, TrxUnit::class);
        $unitFromMemory = $this->repository->findById($trxUnit->id);

        // Assert
        $this->assertCount(1, $units);
        $this->assertNotNull($unitFromMemory);
        $this->assertEquals($trxUnit->id, $unitFromMemory->id);
    }
}
