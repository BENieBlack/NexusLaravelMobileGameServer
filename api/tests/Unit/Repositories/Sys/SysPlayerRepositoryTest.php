<?php

namespace Tests\Unit\Repositories\Sys;

use App\Models\Sys\SysPlayer;
use App\Repositories\Sys\SysPlayerRepository;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class SysPlayerRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected SysPlayerRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SysPlayerRepository();
    }

    /**
     * Test setModel adds model to queue for INSERT
     */
    public function test_set_model_queues_new_player_for_insert(): void
    {
        // Arrange
        $sysPlayer = new SysPlayer([
            'uuid' => 'test-uuid-001',
            'my_id' => 'PLY00001',
            'name' => 'Test Player',
            'level' => 1,
            'level_exp' => 0,
        ]);
        $sysPlayer->exists = false;

        // Act
        $this->repository->setModel($sysPlayer);
        $queuedModels = array_values($this->repository->getQueuedModels());

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals('test-uuid-001', $queuedModels[0]->uuid);
        $this->assertEquals('PLY00001', $queuedModels[0]->my_id);
        $this->assertFalse($queuedModels[0]->exists);
    }

    /**
     * Test setModel adds model to queue for UPDATE
     */
    public function test_set_model_queues_existing_player_for_update(): void
    {
        // Arrange
        $sysPlayer = SysPlayer::create([
            'uuid' => 'test-uuid-002',
            'my_id' => 'PLY00002',
            'name' => 'Original Name',
            'level' => 1,
            'level_exp' => 0,
        ]);

        // Modify the player
        $sysPlayer->name = 'Updated Name';
        $sysPlayer->level = 2;

        // Act
        $this->repository->setModel($sysPlayer);
        $queuedModels = array_values($this->repository->getQueuedModels());

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals('Updated Name', $queuedModels[0]->name);
        $this->assertEquals(2, $queuedModels[0]->level);
        $this->assertTrue($queuedModels[0]->exists);
    }



    /**
     * Test selectById returns player from memory cache
     */
    public function test_select_by_id_returns_player_from_memory_cache(): void
    {
        // Arrange
        $sysPlayer = SysPlayer::create([
            'uuid' => 'test-uuid-004',
            'my_id' => 'PLY00004',
            'name' => 'Cached Player',
            'level' => 5,
            'level_exp' => 100,
        ]);

        // Load into memory cache
        $this->repository->selectById($sysPlayer->id);

        // Act - Should retrieve from memory cache
        $result = $this->repository->selectById($sysPlayer->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($sysPlayer->id, $result->id);
        $this->assertEquals('Cached Player', $result->name);
        $this->assertEquals(5, $result->level);
    }

    /**
     * Test selectById returns null for non-existent player
     */
    public function test_select_by_id_returns_null_for_non_existent_player(): void
    {
        // Act
        $result = $this->repository->selectById(99999);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test selectByMyId returns correct player
     */
    public function test_select_by_my_id_returns_correct_player(): void
    {
        // Arrange
        SysPlayer::create([
            'uuid' => 'test-uuid-005',
            'my_id' => 'PLY00005',
            'name' => 'Player 5',
            'level' => 1,
            'level_exp' => 0,
        ]);

        // Act
        $result = $this->repository->selectByMyId('PLY00005');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('PLY00005', $result->my_id);
        $this->assertEquals('Player 5', $result->name);
    }

    /**
     * Test selectByMyId returns null for non-existent my_id
     */
    public function test_select_by_my_id_returns_null_for_non_existent(): void
    {
        // Act
        $result = $this->repository->selectByMyId('non_existent_id');

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test selectByUuid returns correct player
     */
    public function test_select_by_uuid_returns_correct_player(): void
    {
        // Arrange
        SysPlayer::create([
            'uuid' => 'test-uuid-006',
            'my_id' => 'PLY00006',
            'name' => 'Player 6',
            'level' => 1,
            'level_exp' => 0,
        ]);

        // Act
        $result = $this->repository->selectByUuid('test-uuid-006');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('test-uuid-006', $result->uuid);
        $this->assertEquals('Player 6', $result->name);
    }

    /**
     * Test selectByUuid returns null for non-existent uuid
     */
    public function test_select_by_uuid_returns_null_for_non_existent(): void
    {
        // Act
        $result = $this->repository->selectByUuid('non-existent-uuid');

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test existsByMyId returns true for existing my_id
     */
    public function test_exists_by_my_id_returns_true_for_existing(): void
    {
        // Arrange
        SysPlayer::create([
            'uuid' => 'test-uuid-007',
            'my_id' => 'PLY00007',
            'name' => 'Player 7',
            'level' => 1,
            'level_exp' => 0,
        ]);

        // Act
        $result = $this->repository->existsByMyId('PLY00007');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test existsByMyId returns false for non-existent my_id
     */
    public function test_exists_by_my_id_returns_false_for_non_existent(): void
    {
        // Act
        $result = $this->repository->existsByMyId('non_existent_player');

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test memory cache consistency across multiple operations
     */
    public function test_memory_cache_consistency(): void
    {
        // Arrange
        $sysPlayer = SysPlayer::create([
            'uuid' => 'test-uuid-008',
            'my_id' => 'PLY00008',
            'name' => 'Cache Test Player',
            'level' => 1,
            'level_exp' => 0,
        ]);

        // Act - Load via selectById
        $result1 = $this->repository->selectById($sysPlayer->id);
        
        // Access same player via my_id (should use memory cache)
        $result2 = $this->repository->selectByMyId('PLY00008');
        
        // Access same player via uuid (should use memory cache)
        $result3 = $this->repository->selectByUuid('test-uuid-008');

        // Assert - All should return the same instance
        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertNotNull($result3);
        $this->assertEquals($result1->id, $result2->id);
        $this->assertEquals($result1->id, $result3->id);
    }

    /**
     * Test clearQueue removes all queued models
     */
    public function test_clear_queue_removes_all_queued_models(): void
    {
        // Arrange
        $sysPlayer1 = new SysPlayer([
            'uuid' => 'test-uuid-009',
            'my_id' => 'PLY00009',
            'name' => 'Player 9',
            'level' => 1,
            'level_exp' => 0,
        ]);
        $sysPlayer1->exists = false;

        $sysPlayer2 = new SysPlayer([
            'uuid' => 'test-uuid-010',
            'my_id' => 'PLY00010',
            'name' => 'Player 10',
            'level' => 1,
            'level_exp' => 0,
        ]);
        $sysPlayer2->exists = false;

        $this->repository->setModel($sysPlayer1);
        $this->repository->setModel($sysPlayer2);

        // Act
        $this->repository->clearQueue();
        $queuedModels = $this->repository->getQueuedModels();

        // Assert
        $this->assertCount(0, $queuedModels);
    }

    /**
     * Test multiple models can be queued
     */
    public function test_multiple_models_can_be_queued(): void
    {
        // Arrange
        $players = [];
        for ($i = 1; $i <= 5; $i++) {
            $sysPlayer = new SysPlayer([
                'uuid' => "test-uuid-batch-{$i}",
                'my_id' => sprintf('B%07d', $i), // e.g., B0000001, B0000002 (8 chars)
                'name' => "Batch Player {$i}",
                'level' => 1,
                'level_exp' => 0,
            ]);
            $sysPlayer->exists = false;
            $players[] = $sysPlayer;
        }

        // Act
        foreach ($players as $player) {
            $this->repository->setModel($player);
        }
        $queuedModels = array_values($this->repository->getQueuedModels());

        // Assert
        $this->assertCount(5, $queuedModels);
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals(sprintf('B%07d', $i + 1), $queuedModels[$i]->my_id);
        }
    }

    /**
     * Test createPlayerAndCommit creates player and returns with ID
     */
    public function test_create_player_and_commit_creates_player_and_returns_with_id(): void
    {
        // Act
        $sysPlayer = $this->repository->createPlayerAndCommit();

        // Assert
        $this->assertInstanceOf(SysPlayer::class, $sysPlayer);
        $this->assertNotNull($sysPlayer->id);
        $this->assertNotEmpty($sysPlayer->uuid);
        $this->assertNotEmpty($sysPlayer->my_id);
        $this->assertNotEmpty($sysPlayer->name);
        $this->assertEquals(8, strlen($sysPlayer->my_id)); // my_id should be 8 characters
        
        // Verify it was actually inserted into the database
        $dbPlayer = SysPlayer::find($sysPlayer->id);
        $this->assertNotNull($dbPlayer);
        $this->assertEquals($sysPlayer->uuid, $dbPlayer->uuid);
        $this->assertEquals($sysPlayer->my_id, $dbPlayer->my_id);
    }

    /**
     * Test createPlayerAndCommit generates unique my_id
     */
    public function test_create_player_and_commit_generates_unique_my_id(): void
    {
        // Act - Create multiple players
        $player1 = $this->repository->createPlayerAndCommit();
        $player2 = $this->repository->createPlayerAndCommit();
        $player3 = $this->repository->createPlayerAndCommit();

        // Assert - All my_ids should be unique
        $this->assertNotEquals($player1->my_id, $player2->my_id);
        $this->assertNotEquals($player1->my_id, $player3->my_id);
        $this->assertNotEquals($player2->my_id, $player3->my_id);
        
        // All should have IDs
        $this->assertNotNull($player1->id);
        $this->assertNotNull($player2->id);
        $this->assertNotNull($player3->id);
    }
}
