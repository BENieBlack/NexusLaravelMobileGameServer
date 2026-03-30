<?php

namespace Tests\Unit\Services\Auth;

use App\Domain\Auth\Services\PlayerService;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Repositories\QueryManager;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerTokenRepository;
use Illuminate\Support\Facades\Log;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class PlayerServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private PlayerService $service;

    /**
     * Define database connections to migrate for this test
     */
    protected function connectionsToMigrate(): array
    {
        return [
            'sys' => 'database/migrations/sys',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Repositoryインスタンスを作成してServiceに注入
        $playerRepository = new SysPlayerRepository(new SysPlayer());
        $playerDeviceRepository = new SysPlayerDeviceRepository(new SysPlayerDevice());
        $playerTokenRepository = app(SysPlayerTokenRepository::class);
        
        $this->service = new PlayerService(
            $playerRepository,
            $playerDeviceRepository,
            $playerTokenRepository
        );

        // Suppress log output during tests
        Log::spy();
    }

    /**
     * Test createPlayer creates new player and device successfully
     */
    public function test_create_player_creates_new_player_and_device(): void
    {
        // Arrange
        $deviceId = 'test-device-uuid-12345';
        $deviceInfo = [
            'model' => 'iPhone 13',
            'os' => 'iOS 15.0',
            'app_version' => '1.0.0',
        ];

        // Act
        $result = $this->service->createPlayer($deviceId, $deviceInfo);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sys_player', $result);
        $this->assertArrayHasKey('sys_player_device', $result);

        // sys_playerの検証
        $sysPlayer = $result['sys_player'];
        $this->assertInstanceOf(SysPlayer::class, $sysPlayer);
        $this->assertNotNull($sysPlayer->id);
        $this->assertNotNull($sysPlayer->my_id);
        $this->assertEquals(8, strlen($sysPlayer->my_id)); // my_idは8文字
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{8}$/', $sysPlayer->my_id);

        // sys_player_deviceの検証
        $sysPlayerDevice = $result['sys_player_device'];
        $this->assertInstanceOf(SysPlayerDevice::class, $sysPlayerDevice);
        $this->assertNotNull($sysPlayerDevice->id);
        $this->assertEquals($sysPlayer->id, $sysPlayerDevice->sys_player_id);
        $this->assertEquals($deviceId, $sysPlayerDevice->uuid);
        $this->assertEquals($deviceInfo, $sysPlayerDevice->device_info);

        // データベースに保存されていることを確認
        $this->assertDatabaseHas('sys_player', [
            'id' => $sysPlayer->id,
            'my_id' => $sysPlayer->my_id,
        ], 'sys');

        $this->assertDatabaseHas('sys_player_device', [
            'id' => $sysPlayerDevice->id,
            'sys_player_id' => $sysPlayer->id,
            'uuid' => $deviceId,
        ], 'sys');
    }

    /**
     * Test createPlayer creates new player without device info
     */
    public function test_create_player_without_device_info(): void
    {
        // Arrange
        $deviceId = 'test-device-uuid-67890';

        // Act
        $result = $this->service->createPlayer($deviceId, null);

        // Assert
        $this->assertIsArray($result);
        $sysPlayer = $result['sys_player'];
        $sysPlayerDevice = $result['sys_player_device'];

        $this->assertInstanceOf(SysPlayer::class, $sysPlayer);
        $this->assertInstanceOf(SysPlayerDevice::class, $sysPlayerDevice);
        $this->assertNotNull($sysPlayer->id);
        $this->assertNotNull($sysPlayerDevice->id);
        $this->assertEquals($deviceId, $sysPlayerDevice->uuid);
        $this->assertNull($sysPlayerDevice->device_info);
    }

    /**
     * Test selectByDeviceId returns device when found
     */
    public function test_select_by_device_id_returns_device_when_found(): void
    {
        // Arrange
        $deviceId = 'existing-device-uuid';
        $this->service->createPlayer($deviceId);

        // Act
        $foundDevice = $this->service->selectByDeviceId($deviceId);

        // Assert
        $this->assertInstanceOf(SysPlayerDevice::class, $foundDevice);
        $this->assertEquals($deviceId, $foundDevice->uuid);
    }

    /**
     * Test selectByDeviceId returns null when device not found
     */
    public function test_select_by_device_id_returns_null_when_not_found(): void
    {
        // Arrange
        $deviceId = 'non-existent-device-uuid';

        // Act
        $foundDevice = $this->service->selectByDeviceId($deviceId);

        // Assert
        $this->assertNull($foundDevice);
    }

    /**
     * Test selectById returns player when found
     */
    public function test_select_by_id_returns_player_when_found(): void
    {
        // Arrange
        $result = $this->service->createPlayer('device-for-find-by-id');
        $playerId = $result['sys_player']->id;

        // Act
        $foundPlayer = $this->service->selectById($playerId);

        // Assert
        $this->assertInstanceOf(SysPlayer::class, $foundPlayer);
        $this->assertEquals($playerId, $foundPlayer->id);
    }

    /**
     * Test selectById returns null when player not found
     */
    public function test_select_by_id_returns_null_when_not_found(): void
    {
        // Arrange
        $nonExistentId = 99999;

        // Act
        $foundPlayer = $this->service->selectById($nonExistentId);

        // Assert
        $this->assertNull($foundPlayer);
    }

    /**
     * Test updateLastLogin updates last_login_at successfully
     */
    public function test_update_last_login_updates_timestamp(): void
    {
        // Arrange
        $result = $this->service->createPlayer('device-for-last-login');
        $sysPlayerDevice = $result['sys_player_device'];
        $originalLastLogin = $sysPlayerDevice->last_login_at;

        // 時間を少し進める
        sleep(1);
        
        // Act
        $updateResult = $this->service->updateLastLogin($sysPlayerDevice);

        // Assert
        $this->assertTrue($updateResult);
        
        // データベースから再取得して確認
        $updatedDevice = $this->service->selectByDeviceId($sysPlayerDevice->uuid);
        $this->assertNotNull($updatedDevice);
        $this->assertNotNull($updatedDevice->last_login_at);
        
        // last_login_atが更新されていることを確認（元の値と異なる）
        if ($originalLastLogin !== null) {
            $this->assertNotEquals(
                $originalLastLogin->format('Y-m-d H:i:s'),
                $updatedDevice->last_login_at->format('Y-m-d H:i:s')
            );
        }
    }

    /**
     * Test createPlayer generates unique my_id for each player
     */
    public function test_create_player_generates_unique_my_id(): void
    {
        // Arrange & Act
        $result1 = $this->service->createPlayer('device-uuid-1');
        $result2 = $this->service->createPlayer('device-uuid-2');
        $result3 = $this->service->createPlayer('device-uuid-3');

        // Assert
        $myId1 = $result1['sys_player']->my_id;
        $myId2 = $result2['sys_player']->my_id;
        $myId3 = $result3['sys_player']->my_id;

        // すべて異なることを確認
        $this->assertNotEquals($myId1, $myId2);
        $this->assertNotEquals($myId2, $myId3);
        $this->assertNotEquals($myId1, $myId3);

        // すべて8文字の英数字であることを確認
        $this->assertEquals(8, strlen($myId1));
        $this->assertEquals(8, strlen($myId2));
        $this->assertEquals(8, strlen($myId3));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{8}$/', $myId1);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{8}$/', $myId2);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{8}$/', $myId3);
    }
}
