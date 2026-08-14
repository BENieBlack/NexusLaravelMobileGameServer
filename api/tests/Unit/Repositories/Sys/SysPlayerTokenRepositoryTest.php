<?php

namespace Tests\Unit\Repositories\Sys;

use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Repositories\Sys\SysPlayerTokenRepository;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class SysPlayerTokenRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected SysPlayerTokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SysPlayerTokenRepository;
    }

    /**
     * Test selectValidByHash returns valid token
     */
    public function test_select_valid_by_hash_returns_valid_token(): void
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
            'device_info' => ['os' => 'iOS'],
            'last_login_at' => now(),
        ]);

        $tokenHash = hash('sha256', 'test-token-001');
        $sysPlayerToken = SysPlayerToken::create([
            'sys_player_id' => $sysPlayer->id,
            'sys_player_device_id' => $sysPlayerDevice->id,
            'refresh_token_hash' => $tokenHash,
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
        ]);

        // Act
        $result = $this->repository->selectValidByHash($tokenHash);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($sysPlayerToken->id, $result->id);
    }

    /**
     * Test selectValidByHash returns null for revoked token
     */
    public function test_select_valid_by_hash_returns_null_for_revoked_token(): void
    {
        // Arrange
        $sysPlayer = SysPlayer::create([
            'uuid' => 'test-player-uuid-002',
            'my_id' => 'PLY00002',
            'name' => 'Test Player 2',
            'level' => 1,
            'level_exp' => 0,
        ]);

        $sysPlayerDevice = SysPlayerDevice::create([
            'sys_player_id' => $sysPlayer->id,
            'uuid' => 'test-device-uuid-002',
            'device_info' => ['os' => 'iOS'],
            'last_login_at' => now(),
        ]);

        $tokenHash = hash('sha256', 'test-token-002');
        SysPlayerToken::create([
            'sys_player_id' => $sysPlayer->id,
            'sys_player_device_id' => $sysPlayerDevice->id,
            'refresh_token_hash' => $tokenHash,
            'expires_at' => now()->addDays(30),
            'revoked_at' => now(), // Already revoked
        ]);

        // Act
        $result = $this->repository->selectValidByHash($tokenHash);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test selectValidByHash returns null for expired token
     */
    public function test_select_valid_by_hash_returns_null_for_expired_token(): void
    {
        // Arrange
        $sysPlayer = SysPlayer::create([
            'uuid' => 'test-player-uuid-003',
            'my_id' => 'PLY00003',
            'name' => 'Test Player 3',
            'level' => 1,
            'level_exp' => 0,
        ]);

        $sysPlayerDevice = SysPlayerDevice::create([
            'sys_player_id' => $sysPlayer->id,
            'uuid' => 'test-device-uuid-003',
            'device_info' => ['os' => 'iOS'],
            'last_login_at' => now(),
        ]);

        $tokenHash = hash('sha256', 'test-token-003');
        SysPlayerToken::create([
            'sys_player_id' => $sysPlayer->id,
            'sys_player_device_id' => $sysPlayerDevice->id,
            'refresh_token_hash' => $tokenHash,
            'expires_at' => now()->subDays(1), // Already expired
            'revoked_at' => null,
        ]);

        // Act
        $result = $this->repository->selectValidByHash($tokenHash);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test revokeDeviceTokens revokes all device tokens
     */
    public function test_revoke_device_tokens_revokes_all_device_tokens(): void
    {
        // Arrange
        $sysPlayer = SysPlayer::create([
            'uuid' => 'test-player-uuid-004',
            'my_id' => 'PLY00004',
            'name' => 'Test Player 4',
            'level' => 1,
            'level_exp' => 0,
        ]);

        $sysPlayerDevice = SysPlayerDevice::create([
            'sys_player_id' => $sysPlayer->id,
            'uuid' => 'test-device-uuid-004',
            'device_info' => ['os' => 'iOS'],
            'last_login_at' => now(),
        ]);

        // Create 3 tokens for the device
        for ($i = 1; $i <= 3; $i++) {
            SysPlayerToken::create([
                'sys_player_id' => $sysPlayer->id,
                'sys_player_device_id' => $sysPlayerDevice->id,
                'refresh_token_hash' => hash('sha256', "test-token-004-{$i}"),
                'expires_at' => now()->addDays(30),
                'revoked_at' => null,
            ]);
        }

        // Act
        $count = $this->repository->revokeDeviceTokens($sysPlayerDevice->id);
        $this->flushQueue();

        // Assert
        $this->assertEquals(3, $count);

        // Verify all tokens are revoked
        $tokens = SysPlayerToken::where('sys_player_device_id', $sysPlayerDevice->id)->get();
        foreach ($tokens as $token) {
            $this->assertNotNull($token->revoked_at);
        }
    }

    /**
     * Test selectValidListByPlayerId returns valid tokens
     */
    public function test_select_valid_list_by_player_id_returns_valid_tokens(): void
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

        // Create 2 valid tokens and 1 revoked
        SysPlayerToken::create([
            'sys_player_id' => $sysPlayer->id,
            'sys_player_device_id' => $sysPlayerDevice->id,
            'refresh_token_hash' => hash('sha256', 'test-token-005-1'),
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
        ]);

        SysPlayerToken::create([
            'sys_player_id' => $sysPlayer->id,
            'sys_player_device_id' => $sysPlayerDevice->id,
            'refresh_token_hash' => hash('sha256', 'test-token-005-2'),
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
        ]);

        SysPlayerToken::create([
            'sys_player_id' => $sysPlayer->id,
            'sys_player_device_id' => $sysPlayerDevice->id,
            'refresh_token_hash' => hash('sha256', 'test-token-005-3'),
            'expires_at' => now()->addDays(30),
            'revoked_at' => now(), // Revoked
        ]);

        // Act
        $result = $this->repository->selectValidListByPlayerId($sysPlayer->id);

        // Assert
        $this->assertCount(2, $result); // Only valid tokens
    }
}
