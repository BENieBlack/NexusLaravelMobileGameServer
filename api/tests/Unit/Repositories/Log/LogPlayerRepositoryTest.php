<?php

namespace Tests\Unit\Repositories\Log;

use App\Models\Log\LogPlayer;
use App\Repositories\Log\LogPlayerRepository;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class LogPlayerRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected LogPlayerRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new LogPlayerRepository;
    }

    /**
     * Test createPlayerLog creates log entry and queues it
     */
    public function test_create_player_log_creates_and_queues_log(): void
    {
        // Act
        $this->repository->createPlayerLog(
            uniqueRequestId: 'test-request-001',
            sysPlayerId: 1,
            beforeLevel: 1,
            beforeLevelExp: 0,
            afterLevel: 2,
            afterLevelExp: 50
        );

        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals('test-request-001', $queuedModels[0]->unique_request_id);
        $this->assertEquals(1, $queuedModels[0]->sys_player_id);
        $this->assertEquals(1, $queuedModels[0]->before_level);
        $this->assertEquals(0, $queuedModels[0]->before_level_exp);
        $this->assertEquals(2, $queuedModels[0]->after_level);
        $this->assertEquals(50, $queuedModels[0]->after_level_exp);
        $this->assertNotNull($queuedModels[0]->system_at);
    }

    /**
     * Test setModel queues log entry for INSERT
     */
    public function test_set_model_queues_new_log_for_insert(): void
    {
        // Arrange
        $logPlayer = new LogPlayer([
            'unique_request_id' => 'test-request-002',
            'sys_player_id' => 2,
            'before_level' => 5,
            'before_level_exp' => 100,
            'after_level' => 6,
            'after_level_exp' => 0,
            'system_at' => now(),
        ]);
        $logPlayer->exists = false;

        // Act
        $this->repository->setModel($logPlayer);
        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals('test-request-002', $queuedModels[0]->unique_request_id);
        $this->assertEquals(2, $queuedModels[0]->sys_player_id);
        $this->assertEquals(5, $queuedModels[0]->before_level);
        $this->assertEquals(6, $queuedModels[0]->after_level);
        $this->assertFalse($queuedModels[0]->exists);
    }

    /**
     * Test multiple logs can be queued
     */
    public function test_multiple_logs_can_be_queued(): void
    {
        // Act
        for ($i = 1; $i <= 5; $i++) {
            $this->repository->createPlayerLog(
                uniqueRequestId: "test-request-batch-{$i}",
                sysPlayerId: $i,
                beforeLevel: $i,
                beforeLevelExp: $i * 10,
                afterLevel: $i + 1,
                afterLevelExp: $i * 20
            );
        }

        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(5, $queuedModels);
        for ($i = 0; $i < 5; $i++) {
            $expectedPlayerId = $i + 1;
            $this->assertEquals("test-request-batch-{$expectedPlayerId}", $queuedModels[$i]->unique_request_id);
            $this->assertEquals($expectedPlayerId, $queuedModels[$i]->sys_player_id);
            $this->assertEquals($expectedPlayerId, $queuedModels[$i]->before_level);
            $this->assertEquals($expectedPlayerId + 1, $queuedModels[$i]->after_level);
        }
    }

    /**
     * Test clearQueue removes all queued logs
     */
    public function test_clear_queue_removes_all_queued_logs(): void
    {
        // Arrange
        $this->repository->createPlayerLog(
            uniqueRequestId: 'test-request-003',
            sysPlayerId: 1,
            beforeLevel: 1,
            beforeLevelExp: 0,
            afterLevel: 2,
            afterLevelExp: 50
        );

        $this->repository->createPlayerLog(
            uniqueRequestId: 'test-request-004',
            sysPlayerId: 2,
            beforeLevel: 3,
            beforeLevelExp: 100,
            afterLevel: 4,
            afterLevelExp: 25
        );

        // Act
        $this->repository->clearQueue();
        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(0, $queuedModels);
    }

    /**
     * Test log records various level changes
     */
    public function test_log_records_various_level_changes(): void
    {
        // Act - Level up without exp overflow
        $this->repository->createPlayerLog(
            uniqueRequestId: 'test-level-up-1',
            sysPlayerId: 1,
            beforeLevel: 5,
            beforeLevelExp: 80,
            afterLevel: 6,
            afterLevelExp: 0
        );

        // Act - Exp gain without level up
        $this->repository->createPlayerLog(
            uniqueRequestId: 'test-exp-gain',
            sysPlayerId: 1,
            beforeLevel: 6,
            beforeLevelExp: 0,
            afterLevel: 6,
            afterLevelExp: 50
        );

        // Act - Multiple level ups
        $this->repository->createPlayerLog(
            uniqueRequestId: 'test-multi-level',
            sysPlayerId: 1,
            beforeLevel: 6,
            beforeLevelExp: 90,
            afterLevel: 8,
            afterLevelExp: 20
        );

        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(3, $queuedModels);

        // First log: level up
        $this->assertEquals(5, $queuedModels[0]->before_level);
        $this->assertEquals(6, $queuedModels[0]->after_level);

        // Second log: exp gain only
        $this->assertEquals(6, $queuedModels[1]->before_level);
        $this->assertEquals(6, $queuedModels[1]->after_level);
        $this->assertEquals(50, $queuedModels[1]->after_level_exp);

        // Third log: multiple level ups
        $this->assertEquals(6, $queuedModels[2]->before_level);
        $this->assertEquals(8, $queuedModels[2]->after_level);
    }

    /**
     * Test log entry has required timestamps
     */
    public function test_log_entry_has_required_timestamps(): void
    {
        // Act
        $this->repository->createPlayerLog(
            uniqueRequestId: 'test-timestamp',
            sysPlayerId: 1,
            beforeLevel: 1,
            beforeLevelExp: 0,
            afterLevel: 2,
            afterLevelExp: 0
        );

        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertNotNull($queuedModels[0]->system_at);
        $this->assertNotNull($queuedModels[0]->created_at);
    }

    /**
     * Test repository is marked as normal log (not purchase log)
     */
    public function test_repository_is_normal_log_not_purchase_log(): void
    {
        // Access protected property via reflection for testing
        $reflection = new \ReflectionClass($this->repository);
        $property = $reflection->getProperty('isPurchaseLog');
        $property->setAccessible(true);
        $isPurchaseLog = $property->getValue($this->repository);

        // Assert
        $this->assertFalse($isPurchaseLog);
    }

    /**
     * Test logs can track same player across multiple requests
     */
    public function test_logs_track_same_player_across_requests(): void
    {
        // Act - Track player 1 across 3 different requests
        $this->repository->createPlayerLog(
            uniqueRequestId: 'request-001',
            sysPlayerId: 1,
            beforeLevel: 1,
            beforeLevelExp: 0,
            afterLevel: 2,
            afterLevelExp: 0
        );

        $this->repository->createPlayerLog(
            uniqueRequestId: 'request-002',
            sysPlayerId: 1,
            beforeLevel: 2,
            beforeLevelExp: 0,
            afterLevel: 3,
            afterLevelExp: 25
        );

        $this->repository->createPlayerLog(
            uniqueRequestId: 'request-003',
            sysPlayerId: 1,
            beforeLevel: 3,
            beforeLevelExp: 25,
            afterLevel: 4,
            afterLevelExp: 50
        );

        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(3, $queuedModels);

        // All logs should be for the same player
        $this->assertTrue(collect($queuedModels)->every(fn ($log) => $log->sys_player_id === 1));

        // Each request should have unique request ID
        $requestIds = collect($queuedModels)->pluck('unique_request_id')->toArray();
        $this->assertEquals(['request-001', 'request-002', 'request-003'], $requestIds);

        // Level progression should be sequential
        $this->assertEquals(1, $queuedModels[0]->before_level);
        $this->assertEquals(2, $queuedModels[0]->after_level);
        $this->assertEquals(2, $queuedModels[1]->before_level);
        $this->assertEquals(3, $queuedModels[1]->after_level);
        $this->assertEquals(3, $queuedModels[2]->before_level);
        $this->assertEquals(4, $queuedModels[2]->after_level);
    }
}
