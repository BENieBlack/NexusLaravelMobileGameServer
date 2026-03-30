<?php

namespace Tests\Unit\Services\Auth;

use App\Domain\Auth\DTOs\DtoToken;
use App\Domain\Auth\Services\TokenService;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerTokenRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class TokenServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private TokenService $service;
    private SysPlayerTokenRepository $tokenRepository;

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

        $this->tokenRepository = app(SysPlayerTokenRepository::class);
        $this->service = new TokenService($this->tokenRepository);

        // Suppress log output during tests
        Log::spy();
    }

    /**
     * テスト用のプレイヤーとデバイスを作成するヘルパーメソッド
     */
    private function createPlayerAndDevice(): array
    {
        $playerRepository = new SysPlayerRepository(new SysPlayer());
        $deviceRepository = new SysPlayerDeviceRepository(new SysPlayerDevice());

        $sysPlayer = $playerRepository->createPlayerAndCommit();
        $sysPlayerDevice = $deviceRepository->createDeviceAndCommit(
            $sysPlayer->id,
            'test-device-' . uniqid(),
            ['model' => 'Test Device']
        );

        return [$sysPlayer, $sysPlayerDevice];
    }

    /**
     * Test generateToken creates access and refresh tokens successfully
     */
    public function test_generate_token_creates_tokens_successfully(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice();

        // Act
        [$dtoToken, $sysPlayerToken] = $this->service->generateToken($sysPlayer, $sysPlayerDevice);

        // Assert - DtoToken
        $this->assertInstanceOf(DtoToken::class, $dtoToken);
        $this->assertNotEmpty($dtoToken->accessToken);
        $this->assertNotEmpty($dtoToken->refreshToken);
        $this->assertEquals(3600, $dtoToken->expiresIn); // 1時間

        // Assert - SysPlayerToken
        $this->assertInstanceOf(SysPlayerToken::class, $sysPlayerToken);
        $this->assertNotNull($sysPlayerToken->id);
        $this->assertEquals($sysPlayer->id, $sysPlayerToken->sys_player_id);
        $this->assertEquals($sysPlayerDevice->id, $sysPlayerToken->sys_player_device_id);
        $this->assertNotNull($sysPlayerToken->refresh_token_hash);
        $this->assertNotNull($sysPlayerToken->expires_at);
        $this->assertNull($sysPlayerToken->revoked_at);

        // Assert - refresh_token_hashが正しくハッシュ化されている
        $expectedHash = hash('sha256', $dtoToken->refreshToken);
        $this->assertEquals($expectedHash, $sysPlayerToken->refresh_token_hash);

        // Assert - データベースに保存されている
        $this->assertDatabaseHas('sys_player_token', [
            'id' => $sysPlayerToken->id,
            'sys_player_id' => $sysPlayer->id,
            'sys_player_device_id' => $sysPlayerDevice->id,
        ], 'sys');
    }

    /**
     * Test generateAccessToken creates valid JWT-like token
     */
    public function test_generate_access_token_creates_valid_token(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice();

        // Act
        $accessToken = $this->service->generateAccessToken($sysPlayer, $sysPlayerDevice);

        // Assert
        $this->assertNotEmpty($accessToken);
        $parts = explode('.', $accessToken);
        $this->assertCount(3, $parts); // JWT形式（header.payload.signature）

        // ペイロードをデコードして検証
        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertIsArray($payload);
        $this->assertEquals($sysPlayer->id, $payload['player_id']);
        $this->assertEquals($sysPlayer->uuid, $payload['uuid']);
        $this->assertEquals($sysPlayerDevice->id, $payload['device_id']);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('iat', $payload);
    }

    /**
     * Test validateAccessToken validates valid token successfully
     */
    public function test_validate_access_token_succeeds_for_valid_token(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice();
        $accessToken = $this->service->generateAccessToken($sysPlayer, $sysPlayerDevice);

        // Act
        $payload = $this->service->validateAccessToken($accessToken);

        // Assert
        $this->assertIsArray($payload);
        $this->assertEquals($sysPlayer->id, $payload['player_id']);
        $this->assertEquals($sysPlayer->uuid, $payload['uuid']);
        $this->assertEquals($sysPlayerDevice->id, $payload['device_id']);
    }

    /**
     * Test validateAccessToken returns null for invalid token
     */
    public function test_validate_access_token_returns_null_for_invalid_token(): void
    {
        // Arrange
        $invalidToken = 'invalid.token.here';

        // Act
        $payload = $this->service->validateAccessToken($invalidToken);

        // Assert
        $this->assertNull($payload);
    }

    /**
     * Test validateAccessToken returns null for tampered token
     */
    public function test_validate_access_token_returns_null_for_tampered_token(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice();
        $accessToken = $this->service->generateAccessToken($sysPlayer, $sysPlayerDevice);
        
        // トークンを改ざん
        $parts = explode('.', $accessToken);
        $parts[1] = base64_encode(json_encode(['player_id' => 99999]));
        $tamperedToken = implode('.', $parts);

        // Act
        $payload = $this->service->validateAccessToken($tamperedToken);

        // Assert
        $this->assertNull($payload);
    }

    /**
     * Test validateRefreshToken returns token for valid refresh token
     */
    public function test_validate_refresh_token_succeeds_for_valid_token(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice();
        [$dtoToken, $sysPlayerToken] = $this->service->generateToken($sysPlayer, $sysPlayerDevice);

        // Act
        $validatedToken = $this->service->validateRefreshToken($dtoToken->refreshToken);

        // Assert
        $this->assertInstanceOf(SysPlayerToken::class, $validatedToken);
        $this->assertEquals($sysPlayerToken->id, $validatedToken->id);
        $this->assertTrue($validatedToken->isValid());
    }

    /**
     * Test validateRefreshToken returns null for invalid refresh token
     */
    public function test_validate_refresh_token_returns_null_for_invalid_token(): void
    {
        // Arrange
        $invalidRefreshToken = 'invalid-refresh-token-12345';

        // Act
        $validatedToken = $this->service->validateRefreshToken($invalidRefreshToken);

        // Assert
        $this->assertNull($validatedToken);
    }

    /**
     * Test validateRefreshToken returns null for revoked token
     */
    public function test_validate_refresh_token_returns_null_for_revoked_token(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice();
        [$dtoToken, $sysPlayerToken] = $this->service->generateToken($sysPlayer, $sysPlayerDevice);
        
        // トークンを無効化
        $sysPlayerToken->revoke();

        // Act
        $validatedToken = $this->service->validateRefreshToken($dtoToken->refreshToken);

        // Assert
        $this->assertNull($validatedToken);
    }

    /**
     * Test revokeDeviceTokens revokes all tokens for device
     */
    public function test_revoke_device_tokens_revokes_all_tokens(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice();
        
        // 複数のトークンを作成
        [$dtoToken1, $sysPlayerToken1] = $this->service->generateToken($sysPlayer, $sysPlayerDevice);
        [$dtoToken2, $sysPlayerToken2] = $this->service->generateToken($sysPlayer, $sysPlayerDevice);
        [$dtoToken3, $sysPlayerToken3] = $this->service->generateToken($sysPlayer, $sysPlayerDevice);

        // すべて有効であることを確認
        $this->assertNotNull($this->service->validateRefreshToken($dtoToken1->refreshToken));
        $this->assertNotNull($this->service->validateRefreshToken($dtoToken2->refreshToken));
        $this->assertNotNull($this->service->validateRefreshToken($dtoToken3->refreshToken));

        // Act
        $revokedCount = $this->service->revokeDeviceTokens($sysPlayerDevice);

        // Assert
        $this->assertEquals(3, $revokedCount);

        // データベースから直接取得して無効化を確認
        $token1FromDb = SysPlayerToken::find($sysPlayerToken1->id);
        $token2FromDb = SysPlayerToken::find($sysPlayerToken2->id);
        $token3FromDb = SysPlayerToken::find($sysPlayerToken3->id);

        $this->assertNotNull($token1FromDb->revoked_at);
        $this->assertNotNull($token2FromDb->revoked_at);
        $this->assertNotNull($token3FromDb->revoked_at);

        // データベースでも無効化されていることを確認
        $this->assertDatabaseMissing('sys_player_token', [
            'id' => $sysPlayerToken1->id,
            'revoked_at' => null,
        ], 'sys');
        $this->assertDatabaseMissing('sys_player_token', [
            'id' => $sysPlayerToken2->id,
            'revoked_at' => null,
        ], 'sys');
        $this->assertDatabaseMissing('sys_player_token', [
            'id' => $sysPlayerToken3->id,
            'revoked_at' => null,
        ], 'sys');
    }

    /**
     * Test rotateToken creates new token and revokes old one
     */
    public function test_rotate_token_creates_new_token_and_revokes_old(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice();
        [$oldDtoToken, $oldSysPlayerToken] = $this->service->generateToken($sysPlayer, $sysPlayerDevice);

        // リレーションを読み込む（rotateTokenで使用されるため）
        $oldSysPlayerToken->load(['player', 'device']);

        // 時間を少し進めて、異なるトークンが生成されるようにする
        sleep(1);

        // Act
        [$newDtoToken, $newSysPlayerToken] = $this->service->rotateToken($oldSysPlayerToken);

        // Assert - 新しいトークンが作成されている
        $this->assertInstanceOf(DtoToken::class, $newDtoToken);
        $this->assertInstanceOf(SysPlayerToken::class, $newSysPlayerToken);
        $this->assertNotEquals($oldDtoToken->refreshToken, $newDtoToken->refreshToken);
        $this->assertNotEquals($oldSysPlayerToken->id, $newSysPlayerToken->id);

        // Assert - 古いトークンが無効化されている
        $oldSysPlayerToken->refresh();
        $this->assertNotNull($oldSysPlayerToken->revoked_at);
        $this->assertFalse($oldSysPlayerToken->isValid());

        // Assert - 古いトークンで検証すると失敗
        $validatedOldToken = $this->service->validateRefreshToken($oldDtoToken->refreshToken);
        $this->assertNull($validatedOldToken);

        // Assert - 新しいトークンで検証すると成功
        $validatedNewToken = $this->service->validateRefreshToken($newDtoToken->refreshToken);
        $this->assertInstanceOf(SysPlayerToken::class, $validatedNewToken);
        $this->assertEquals($newSysPlayerToken->id, $validatedNewToken->id);
    }

    /**
     * Test generateToken creates token with correct expiration
     */
    public function test_generate_token_creates_token_with_correct_expiration(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice();

        // Act
        [$dtoToken, $sysPlayerToken] = $this->service->generateToken($sysPlayer, $sysPlayerDevice);

        // Assert - リフレッシュトークンの有効期限が30日後であることを確認
        $expectedExpiresAt = CarbonImmutable::now()->addDays(30);
        $actualExpiresAt = CarbonImmutable::parse($sysPlayerToken->expires_at);

        // 数秒の誤差は許容
        $this->assertTrue(
            $actualExpiresAt->diffInSeconds($expectedExpiresAt) < 5,
            "Expected expires_at to be approximately 30 days from now"
        );

        // Assert - アクセストークンの有効期限が1時間（3600秒）であることを確認
        $this->assertEquals(3600, $dtoToken->expiresIn);
    }

    /**
     * Test multiple tokens can be generated for same player
     */
    public function test_multiple_tokens_can_be_generated_for_same_player(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice();

        // Act - 複数のトークンを生成
        [$dtoToken1, $sysPlayerToken1] = $this->service->generateToken($sysPlayer, $sysPlayerDevice);
        [$dtoToken2, $sysPlayerToken2] = $this->service->generateToken($sysPlayer, $sysPlayerDevice);
        [$dtoToken3, $sysPlayerToken3] = $this->service->generateToken($sysPlayer, $sysPlayerDevice);

        // Assert - すべてのトークンが異なることを確認
        $this->assertNotEquals($dtoToken1->refreshToken, $dtoToken2->refreshToken);
        $this->assertNotEquals($dtoToken2->refreshToken, $dtoToken3->refreshToken);
        $this->assertNotEquals($dtoToken1->refreshToken, $dtoToken3->refreshToken);

        $this->assertNotEquals($sysPlayerToken1->id, $sysPlayerToken2->id);
        $this->assertNotEquals($sysPlayerToken2->id, $sysPlayerToken3->id);
        $this->assertNotEquals($sysPlayerToken1->id, $sysPlayerToken3->id);

        // Assert - すべてのトークンが有効
        $validatedToken1 = $this->service->validateRefreshToken($dtoToken1->refreshToken);
        $validatedToken2 = $this->service->validateRefreshToken($dtoToken2->refreshToken);
        $validatedToken3 = $this->service->validateRefreshToken($dtoToken3->refreshToken);

        $this->assertNotNull($validatedToken1);
        $this->assertNotNull($validatedToken2);
        $this->assertNotNull($validatedToken3);
    }
}
