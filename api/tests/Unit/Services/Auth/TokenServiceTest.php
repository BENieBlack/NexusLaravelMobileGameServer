<?php

namespace Tests\Unit\Services\Auth;

use NexusAuth\DTOs\TokenDto;
use NexusAuth\Services\TokenService;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use NexusUnitOfWork\Persistence\QueryManager;
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
     * トークンモデルファクトリ
     */
    private function tokenModelFactory(int $playerId, int $deviceId, string $tokenHash, string $expiresAt): SysPlayerToken
    {
        return new SysPlayerToken([
            'sys_player_id' => $playerId,
            'sys_player_device_id' => $deviceId,
            'refresh_token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

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
        $this->service = new TokenService(
            $this->tokenRepository,
            config('app.key'),
            3600,
            30
        );

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
        
        $sysPlayerDevice = new SysPlayerDevice([
            'sys_player_id' => $sysPlayer->getId(),
            'uuid' => 'test-device-' . uniqid(),
            'device_info' => ['model' => 'Test Device'],
            'last_login_at' => now(),
        ]);
        $deviceRepository->setModel($sysPlayerDevice);
        
        // デバイスをDBに保存（バッチINSERT）
        app(QueryManager::class)->execAllQuery();

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
        [$dtoToken, $sysPlayerToken] = $this->service->generateToken($sysPlayer, $sysPlayerDevice, $this->tokenModelFactory(...));
        
        // トークンをDBに保存（バッチINSERT）
        app(QueryManager::class)->execAllQuery();

        // Assert - DtoToken
        $this->assertInstanceOf(TokenDto::class, $dtoToken);
        $this->assertNotEmpty($dtoToken->accessToken);
        $this->assertNotEmpty($dtoToken->refreshToken);
        $this->assertEquals(3600, $dtoToken->expiresIn); // 1時間

        // Assert - SysPlayerToken
        $this->assertInstanceOf(SysPlayerToken::class, $sysPlayerToken);
        $this->assertEquals($sysPlayer->getId(), $sysPlayerToken->getSysPlayerId());
        $this->assertEquals($sysPlayerDevice->id, $sysPlayerToken->sys_player_device_id);
        $this->assertNotNull($sysPlayerToken->getRefreshTokenHash());
        $this->assertNotNull($sysPlayerToken->getExpiresAt());
        $this->assertNull($sysPlayerToken->getRevokedAt());

        // Assert - refresh_token_hashが正しくハッシュ化されている
        $expectedHash = hash('sha256', $dtoToken->refreshToken);
        $this->assertEquals($expectedHash, $sysPlayerToken->getRefreshTokenHash());

        // Assert - データベースに保存されている
        $this->assertDatabaseHas('sys_player_token', [
            'sys_player_id' => $sysPlayer->getId(),
            'sys_player_device_id' => $sysPlayerDevice->id,
            'refresh_token_hash' => $expectedHash,
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
        $this->assertEquals($sysPlayer->getId(), $payload['player_id']);
        $this->assertEquals($sysPlayer->uuid, $payload['uuid']);
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
        $this->assertEquals($sysPlayer->getId(), $payload['player_id']);
        $this->assertEquals($sysPlayer->uuid, $payload['uuid']);
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
        [$dtoToken, $sysPlayerToken] = $this->service->generateToken($sysPlayer, $sysPlayerDevice, $this->tokenModelFactory(...));

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
        [$dtoToken, $sysPlayerToken] = $this->service->generateToken($sysPlayer, $sysPlayerDevice, $this->tokenModelFactory(...));
        
        // トークンを無効化
        $sysPlayerToken->revoke();

        // Act
        $validatedToken = $this->service->validateRefreshToken($dtoToken->refreshToken);

        // Assert
        $this->assertNull($validatedToken);
    }

    /**
     * Test revokePlayerTokens revokes all tokens for player
     */
    public function test_revoke_device_tokens_revokes_all_tokens(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice();
        
        // 複数のトークンを作成
        [$dtoToken1, $sysPlayerToken1] = $this->service->generateToken($sysPlayer, $sysPlayerDevice, $this->tokenModelFactory(...));
        [$dtoToken2, $sysPlayerToken2] = $this->service->generateToken($sysPlayer, $sysPlayerDevice, $this->tokenModelFactory(...));
        [$dtoToken3, $sysPlayerToken3] = $this->service->generateToken($sysPlayer, $sysPlayerDevice, $this->tokenModelFactory(...));
        
        // トークンをDBに保存（バッチINSERT）
        app(QueryManager::class)->execAllQuery();

        // すべて有効であることを確認
        $this->assertNotNull($this->service->validateRefreshToken($dtoToken1->refreshToken));
        $this->assertNotNull($this->service->validateRefreshToken($dtoToken2->refreshToken));
        $this->assertNotNull($this->service->validateRefreshToken($dtoToken3->refreshToken));

        // Act
        $revokedCount = $this->service->revokePlayerTokens($sysPlayer->getId());
        
        // 無効化をDBに保存
        app(QueryManager::class)->execAllQuery();

        // Assert
        $this->assertEquals(3, $revokedCount);

        // Assert - トークンは物理削除されているため、DBから取得できない
        $tokens = SysPlayerToken::where('sys_player_id', $sysPlayer->getId())->get();
        $this->assertCount(0, $tokens);
    }

    /**
     * Test rotateToken creates new token and revokes old one
     */
    public function test_rotate_token_creates_new_token_and_revokes_old(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice();
        [$oldDtoToken, $oldSysPlayerToken] = $this->service->generateToken($sysPlayer, $sysPlayerDevice, $this->tokenModelFactory(...));
        
        // 古いトークンをDBに保存
        app(QueryManager::class)->execAllQuery();

        // リレーションを読み込む（rotateTokenで使用されるため）
        $oldSysPlayerToken->load(['player', 'device']);

        // 時間を少し進めて、異なるトークンが生成されるようにする
        sleep(1);

        // Act
        [$newDtoToken, $newSysPlayerToken] = $this->service->rotateToken(
            $oldSysPlayerToken,
            $sysPlayer,
            $sysPlayerDevice,
            $this->tokenModelFactory(...)
        );
        
        // 新しいトークンと古いトークンの無効化をDBに保存
        app(QueryManager::class)->execAllQuery();

        // Assert - 新しいトークンが作成されている
        $this->assertInstanceOf(TokenDto::class, $newDtoToken);
        $this->assertInstanceOf(SysPlayerToken::class, $newSysPlayerToken);
        $this->assertNotEquals($oldDtoToken->refreshToken, $newDtoToken->refreshToken);
        $this->assertNotEquals($oldSysPlayerToken->getRefreshTokenHash(), $newSysPlayerToken->getRefreshTokenHash());

        // Assert - 古いトークンは削除されている（DBから取得できない）
        $oldTokenFromDb = SysPlayerToken::find($oldSysPlayerToken->getId());
        $this->assertNull($oldTokenFromDb);

        // Assert - 古いトークンで検証すると失敗
        $validatedOldToken = $this->service->validateRefreshToken($oldDtoToken->refreshToken);
        $this->assertNull($validatedOldToken);

        // Assert - 新しいトークンで検証すると成功
        $validatedNewToken = $this->service->validateRefreshToken($newDtoToken->refreshToken);
        $this->assertInstanceOf(SysPlayerToken::class, $validatedNewToken);
        $this->assertEquals($newSysPlayerToken->getRefreshTokenHash(), $validatedNewToken->getRefreshTokenHash());
    }

    /**
     * Test generateToken creates token with correct expiration
     */
    public function test_generate_token_creates_token_with_correct_expiration(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice();

        // Act
        [$dtoToken, $sysPlayerToken] = $this->service->generateToken($sysPlayer, $sysPlayerDevice, $this->tokenModelFactory(...));

        // Assert - リフレッシュトークンの有効期限が30日後であることを確認
        $expectedExpiresAt = CarbonImmutable::now()->addDays(30);
        $actualExpiresAt = CarbonImmutable::parse($sysPlayerToken->getExpiresAt());

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
        [$dtoToken1, $sysPlayerToken1] = $this->service->generateToken($sysPlayer, $sysPlayerDevice, $this->tokenModelFactory(...));
        [$dtoToken2, $sysPlayerToken2] = $this->service->generateToken($sysPlayer, $sysPlayerDevice, $this->tokenModelFactory(...));
        [$dtoToken3, $sysPlayerToken3] = $this->service->generateToken($sysPlayer, $sysPlayerDevice, $this->tokenModelFactory(...));
        
        // トークンをDBに保存（バッチINSERT）
        app(QueryManager::class)->execAllQuery();

        // Assert - すべてのトークンが異なることを確認
        $this->assertNotEquals($dtoToken1->refreshToken, $dtoToken2->refreshToken);
        $this->assertNotEquals($dtoToken2->refreshToken, $dtoToken3->refreshToken);
        $this->assertNotEquals($dtoToken1->refreshToken, $dtoToken3->refreshToken);

        $this->assertNotEquals($sysPlayerToken1->refresh_token_hash, $sysPlayerToken2->refresh_token_hash);
        $this->assertNotEquals($sysPlayerToken2->refresh_token_hash, $sysPlayerToken3->refresh_token_hash);
        $this->assertNotEquals($sysPlayerToken1->refresh_token_hash, $sysPlayerToken3->refresh_token_hash);

        // Assert - すべてのトークンが有効
        $validatedToken1 = $this->service->validateRefreshToken($dtoToken1->refreshToken);
        $validatedToken2 = $this->service->validateRefreshToken($dtoToken2->refreshToken);
        $validatedToken3 = $this->service->validateRefreshToken($dtoToken3->refreshToken);

        $this->assertNotNull($validatedToken1);
        $this->assertNotNull($validatedToken2);
        $this->assertNotNull($validatedToken3);
    }
}
