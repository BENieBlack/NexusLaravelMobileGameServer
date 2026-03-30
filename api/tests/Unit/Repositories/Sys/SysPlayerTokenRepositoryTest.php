<?php

namespace Tests\Unit\Repositories\Sys;

use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Repositories\Sys\SysPlayerTokenRepository;
use Carbon\CarbonImmutable;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class SysPlayerTokenRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected SysPlayerTokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SysPlayerTokenRepository();
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

    /**
     * Test createTokenAndCommit creates token and returns with ID
     */
    public function test_create_token_and_commit_creates_token_and_returns_with_id(): void
    {
        // Arrange
        $sysPlayer = SysPlayer::create([
            'uuid' => 'test-player-uuid-006',
            'my_id' => 'PLY00006',
            'name' => 'Test Player 6',
            'level' => 1,
            'level_exp' => 0,
        ]);

        $sysPlayerDevice = SysPlayerDevice::create([
            'sys_player_id' => $sysPlayer->id,
            'uuid' => 'test-device-uuid-006',
            'device_info' => ['os' => 'iOS'],
            'last_login_at' => now(),
        ]);

        $tokenHash = hash('sha256', 'test-token-006');
        $expiresAt = CarbonImmutable::now()->addDays(30);

        // Act
        $sysPlayerToken = $this->repository->createTokenAndCommit(
            $sysPlayer->id,
            $sysPlayerDevice->id,
            $tokenHash,
            $expiresAt
        );

        // Assert
        $this->assertInstanceOf(SysPlayerToken::class, $sysPlayerToken);
        $this->assertNotNull($sysPlayerToken->id);
        $this->assertEquals($sysPlayer->id, $sysPlayerToken->sys_player_id);
        $this->assertEquals($sysPlayerDevice->id, $sysPlayerToken->sys_player_device_id);
        $this->assertEquals($tokenHash, $sysPlayerToken->refresh_token_hash);
        $this->assertNull($sysPlayerToken->revoked_at);

        // Verify it was actually inserted into the database
        $dbToken = SysPlayerToken::find($sysPlayerToken->id);
        $this->assertNotNull($dbToken);
        $this->assertEquals($tokenHash, $dbToken->refresh_token_hash);
    }

    /**
     * Test createTokenAndCommit with different expiration dates
     */
    public function test_create_token_and_commit_with_different_expiration_dates(): void
    {
        // Arrange
        $sysPlayer = SysPlayer::create([
            'uuid' => 'test-player-uuid-007',
            'my_id' => 'PLY00007',
            'name' => 'Test Player 7',
            'level' => 1,
            'level_exp' => 0,
        ]);

        $sysPlayerDevice = SysPlayerDevice::create([
            'sys_player_id' => $sysPlayer->id,
            'uuid' => 'test-device-uuid-007',
            'device_info' => ['os' => 'iOS'],
            'last_login_at' => now(),
        ]);

        $tokenHash1 = hash('sha256', 'test-token-007-1');
        $expiresAt1 = CarbonImmutable::now()->addDays(7);

        $tokenHash2 = hash('sha256', 'test-token-007-2');
        $expiresAt2 = CarbonImmutable::now()->addDays(90);

        // Act
        $token1 = $this->repository->createTokenAndCommit(
            $sysPlayer->id,
            $sysPlayerDevice->id,
            $tokenHash1,
            $expiresAt1
        );

        $token2 = $this->repository->createTokenAndCommit(
            $sysPlayer->id,
            $sysPlayerDevice->id,
            $tokenHash2,
            $expiresAt2
        );

        // Assert
        $this->assertNotNull($token1->id);
        $this->assertNotNull($token2->id);
        $this->assertNotEquals($token1->id, $token2->id);
        
        // Verify expiration dates are correct
        $this->assertEquals(
            $expiresAt1->format('Y-m-d H:i:s'),
            $token1->expires_at->format('Y-m-d H:i:s')
        );
        $this->assertEquals(
            $expiresAt2->format('Y-m-d H:i:s'),
            $token2->expires_at->format('Y-m-d H:i:s')
        );
    }
}
