<?php

namespace Tests\Unit\Repositories\Sys;

use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class SysPlayerDeviceRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected SysPlayerDeviceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SysPlayerDeviceRepository;
    }

    /**
     * Test selectByDeviceId returns device by UUID
     */
    public function test_select_by_device_id_returns_device(): void
    {
        // Arrange
        $sysPlayer = SysPlayer::create([
            'uuid' => 'test-player-uuid-001',
            'my_id' => 'PLY00001',
            'name' => 'Test Player',
            'level' => 1,
            'level_exp' => 0,
        ]);

        $sysPlayerDevice = SysPlayerDevice::create([
            'sys_player_id' => $sysPlayer->id,
            'uuid' => 'test-device-uuid-001',
            'device_info' => ['os' => 'iOS', 'version' => '17.0'],
            'last_login_at' => now(),
        ]);

        // Act
        $result = $this->repository->selectByDeviceId('test-device-uuid-001');

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($sysPlayerDevice->id, $result->id);
        $this->assertEquals('test-device-uuid-001', $result->uuid);
    }

    /**
     * Test selectByDeviceId returns null for non-existent device
     */
    public function test_select_by_device_id_returns_null_for_non_existent(): void
    {
        // Act
        $result = $this->repository->selectByDeviceId('non-existent-device');

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test selectListByPlayerId returns devices for player
     */
    public function test_select_list_by_player_id_returns_devices(): void
    {
        // Arrange
        $sysPlayer = SysPlayer::create([
            'uuid' => 'test-player-uuid-002',
            'my_id' => 'PLY00002',
            'name' => 'Test Player 2',
            'level' => 1,
            'level_exp' => 0,
        ]);

        SysPlayerDevice::create([
            'sys_player_id' => $sysPlayer->id,
            'uuid' => 'test-device-uuid-002-1',
            'device_info' => ['os' => 'iOS', 'version' => '17.0'],
            'last_login_at' => now(),
        ]);

        SysPlayerDevice::create([
            'sys_player_id' => $sysPlayer->id,
            'uuid' => 'test-device-uuid-002-2',
            'device_info' => ['os' => 'Android', 'version' => '14.0'],
            'last_login_at' => now(),
        ]);

        // Act
        $result = $this->repository->selectListByPlayerId($sysPlayer->id);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('test-device-uuid-002-1', $result[0]->uuid);
        $this->assertEquals('test-device-uuid-002-2', $result[1]->uuid);
    }

    /**
     * Test memory cache consistency
     */
    public function test_memory_cache_consistency(): void
    {
        // Arrange
        $sysPlayer = SysPlayer::create([
            'uuid' => 'test-player-uuid-005',
            'my_id' => 'PLY00005',
            'name' => 'Test Player 5',
            'level' => 1,
            'level_exp' => 0,
        ]);

        $sysPlayerDevice = SysPlayerDevice::create([
            'sys_player_id' => $sysPlayer->id,
            'uuid' => 'test-device-uuid-005',
            'device_info' => ['os' => 'iOS'],
            'last_login_at' => now(),
        ]);

        // Act - Load via selectByDeviceId
        $result1 = $this->repository->selectByDeviceId('test-device-uuid-005');

        // Access same device via selectListByPlayerId (should use memory cache)
        $result2 = $this->repository->selectListByPlayerId($sysPlayer->id);

        // Assert
        $this->assertNotNull($result1);
        $this->assertCount(1, $result2);
        $this->assertEquals($result1->id, $result2[0]->id);
    }
}
