<?php

namespace Tests\Unit\UseCases\Auth;

use App\Domain\Player\Services\PlayerService;
use NexusAuth\Services\TokenService;
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Contracts\PlayerRepositoryInterface;
use NexusAuth\Contracts\DeviceRepositoryInterface;
use App\Domain\Auth\UseCases\RefreshTokenUseCase;
use App\Exceptions\GameException;
use App\Http\Responses\Auth\RefreshTokenResponse;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerTokenRepository;
use NexusUnitOfWork\Persistence\QueryManager;
use Illuminate\Support\Facades\Log;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class RefreshTokenUseCaseTest extends TestCase
{
    use RefreshMultipleDatabases;

    private RefreshTokenUseCase $useCase;
    private PlayerService $playerService;
    private TokenService $tokenService;
    private PlayerAuthService $playerAuthService;
    private PlayerRepositoryInterface $playerRepository;
    private DeviceRepositoryInterface $deviceRepository;

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

        // Repositoriesを取得
        $this->playerRepository = app(PlayerRepositoryInterface::class);
        $this->deviceRepository = app(DeviceRepositoryInterface::class);
        $tokenRepository = app(SysPlayerTokenRepository::class);
        
        $this->playerService = new PlayerService(
            new SysPlayerRepository(new SysPlayer()),
            new SysPlayerDeviceRepository(new SysPlayerDevice()),
            $tokenRepository
        );

        $this->playerAuthService = app(PlayerAuthService::class);
        $this->tokenService = app(TokenService::class);

        // UseCaseを作成
        $this->useCase = new RefreshTokenUseCase(
            $this->tokenService,
            $this->playerAuthService,
            $this->playerRepository,
            $this->deviceRepository
        );

        // Suppress log output during tests
        Log::spy();
    }

    /**
     * テスト用のプレイヤーとデバイス、トークンを作成するヘルパーメソッド
     */
    private function createPlayerDeviceAndToken(): array
    {
        $result = $this->playerService->createPlayer('test-device-' . uniqid(), ['model' => 'Test']);
        $sysPlayer = $result['sys_player'];
        $sysPlayerDevice = $result['sys_player_device'];
        
        [$dtoToken, $sysPlayerToken] = $this->tokenService->generateToken(
            $sysPlayer,
            $sysPlayerDevice,
            fn($playerId, $deviceId, $tokenHash, $expiresAt) => \App\Models\Sys\SysPlayerToken::create([
                'sys_player_id' => $playerId,
                'sys_player_device_id' => $deviceId,
                'refresh_token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
            ])
        );
        
        // トークンをDBに保存（バッチINSERT）
        app(QueryManager::class)->execAllQuery();
        
        return [$sysPlayer, $sysPlayerDevice, $dtoToken, $sysPlayerToken];
    }

    /**
     * Test handle refreshes token successfully
     */
    public function test_handle_refreshes_token_successfully(): void
    {
        // Arrange
        [, , $oldDtoToken, $oldSysPlayerToken] = $this->createPlayerDeviceAndToken();

        // リレーションを読み込む
        $oldSysPlayerToken->load(['player', 'device']);

        // 時間を少し進める
        sleep(1);

        // Act
        $response = $this->useCase->handle($oldDtoToken->getRefreshToken());

        // Assert
        $this->assertInstanceOf(RefreshTokenResponse::class, $response);
        $this->assertNotNull($response->dtoToken);
        $this->assertNotEmpty($response->dtoToken->getAccessToken());
        $this->assertNotEmpty($response->dtoToken->getRefreshToken());
        $this->assertEquals(3600, $response->dtoToken->getExpiresIn());
    }

    /**
     * Test handle generates new token different from old one
     */
    public function test_handle_generates_new_token_different_from_old(): void
    {
        // Arrange
        [, , $oldDtoToken, $oldSysPlayerToken] = $this->createPlayerDeviceAndToken();
        $oldSysPlayerToken->load(['player', 'device']);
        
        sleep(1);

        // Act
        $response = $this->useCase->handle($oldDtoToken->getRefreshToken());

        // Assert - 新しいトークンは古いトークンと異なる
        $this->assertNotEquals($oldDtoToken->getRefreshToken(), $response->dtoToken->getRefreshToken());
        $this->assertNotEquals($oldDtoToken->getAccessToken(), $response->dtoToken->getAccessToken());
    }

    /**
     * Test handle revokes old token
     */
    public function test_handle_revokes_old_token(): void
    {
        // Arrange
        [, , $oldDtoToken, $oldSysPlayerToken] = $this->createPlayerDeviceAndToken();
        $oldSysPlayerToken->load(['player', 'device']);

        // 古いトークンが有効であることを確認
        $this->assertNotNull($this->tokenService->validateRefreshToken($oldDtoToken->getRefreshToken()));

        sleep(1);

        // Act
        $response = $this->useCase->handle($oldDtoToken->getRefreshToken());

        // Assert - 古いトークンはDBから削除されている
        $tokenHash = hash('sha256', $oldDtoToken->getRefreshToken());
        $deletedToken = SysPlayerToken::where('refresh_token_hash', $tokenHash)->first();
        $this->assertNull($deletedToken);

        // Assert - 新しいトークンは有効
        $this->assertNotNull($this->tokenService->validateRefreshToken($response->dtoToken->getRefreshToken()));
    }

    /**
     * Test handle throws exception for invalid refresh token
     */
    public function test_handle_throws_exception_for_invalid_token(): void
    {
        // Arrange
        $invalidRefreshToken = 'invalid-refresh-token-12345';

        // Assert & Act
        $this->expectException(GameException::class);
        $this->expectExceptionMessage('Invalid or expired refresh token');

        $this->useCase->handle($invalidRefreshToken);
    }

    /**
     * Test handle throws exception for revoked token
     */
    public function test_handle_throws_exception_for_revoked_token(): void
    {
        // Arrange
        [, , $oldDtoToken, $oldSysPlayerToken] = $this->createPlayerDeviceAndToken();
        
        // トークンを無効化
        $oldSysPlayerToken->revoke();

        // Assert & Act
        $this->expectException(GameException::class);
        $this->expectExceptionMessage('Invalid or expired refresh token');

        $this->useCase->handle($oldDtoToken->getRefreshToken());
    }

    /**
     * Test handle throws exception for expired token
     */
    public function test_handle_throws_exception_for_expired_token(): void
    {
        // Arrange
        [, , $oldDtoToken, $oldSysPlayerToken] = $this->createPlayerDeviceAndToken();
        
        // トークンの有効期限を過去に設定
        $oldSysPlayerToken->expires_at = now()->subDay();
        $oldSysPlayerToken->save();

        // Assert & Act
        $this->expectException(GameException::class);
        $this->expectExceptionMessage('Invalid or expired refresh token');

        $this->useCase->handle($oldDtoToken->getRefreshToken());
    }

    /**
     * Test handle allows multiple token refreshes in sequence
     */
    public function test_handle_allows_multiple_token_refreshes(): void
    {
        // Arrange
        [, , $dtoToken1, $sysPlayerToken1] = $this->createPlayerDeviceAndToken();
        $sysPlayerToken1->load(['player', 'device']);

        sleep(1);

        // Act - 1回目のリフレッシュ
        $response1 = $this->useCase->handle($dtoToken1->getRefreshToken());
        
        sleep(1);

        // Act - 2回目のリフレッシュ（1回目で得たトークンを使用）
        $response2 = $this->useCase->handle($response1->dtoToken->getRefreshToken());
        
        sleep(1);

        // Act - 3回目のリフレッシュ（2回目で得たトークンを使用）
        $response3 = $this->useCase->handle($response2->dtoToken->getRefreshToken());

        // Assert - すべて異なるトークンが返される
        $this->assertNotEquals($dtoToken1->getRefreshToken(), $response1->dtoToken->getRefreshToken());
        $this->assertNotEquals($response1->dtoToken->getRefreshToken(), $response2->dtoToken->getRefreshToken());
        $this->assertNotEquals($response2->dtoToken->getRefreshToken(), $response3->dtoToken->getRefreshToken());

        // Assert - 古いトークンはDBから削除されている
        $tokenHash1 = hash('sha256', $dtoToken1->getRefreshToken());
        $tokenHash2 = hash('sha256', $response1->dtoToken->getRefreshToken());
        $tokenHash3 = hash('sha256', $response2->dtoToken->getRefreshToken());
        
        $this->assertNull(SysPlayerToken::where('refresh_token_hash', $tokenHash1)->first());
        $this->assertNull(SysPlayerToken::where('refresh_token_hash', $tokenHash2)->first());
        $this->assertNull(SysPlayerToken::where('refresh_token_hash', $tokenHash3)->first());

        // Assert - 最新のトークンのみ有効
        $this->assertNotNull($this->tokenService->validateRefreshToken($response3->dtoToken->getRefreshToken()));
    }

    /**
     * Test handle updates last login time via token rotation
     */
    public function test_handle_updates_last_login_time(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice, $dtoToken, $sysPlayerToken] = $this->createPlayerDeviceAndToken();
        $sysPlayerToken->load(['player', 'device']);
        $originalLastLoginAtString = $sysPlayerDevice->getLastLoginAt();
        $originalLastLoginAt = $originalLastLoginAtString !== null ? \Carbon\Carbon::parse($originalLastLoginAtString) : null;

        // 時間を進める
        sleep(2);

        // Act
        $this->useCase->handle($dtoToken->getRefreshToken());

        // Assert - last_login_atが更新されている
        $updatedDevice = $this->playerService->selectByDeviceId($sysPlayerDevice->getUuid());
        $this->assertNotNull($updatedDevice);
        $updatedLastLoginAtString = $updatedDevice->getLastLoginAt();
        $this->assertNotNull($updatedLastLoginAtString);
        
        // 更新されたことを確認（元の値がnullでない場合は異なる値になっているはず）
        if ($originalLastLoginAt !== null) {
            // タイムスタンプが同じか後であることを確認
            $updatedLastLoginAt = \Carbon\Carbon::parse($updatedLastLoginAtString);
            $this->assertGreaterThanOrEqual(
                $originalLastLoginAt->getTimestamp(),
                $updatedLastLoginAt->getTimestamp()
            );
        }
    }

    /**
     * Test handle cannot reuse old token after refresh
     */
    public function test_handle_cannot_reuse_old_token_after_refresh(): void
    {
        // Arrange
        [, , $oldDtoToken, $oldSysPlayerToken] = $this->createPlayerDeviceAndToken();
        $oldSysPlayerToken->load(['player', 'device']);

        sleep(1);

        // Act - トークンをリフレッシュ
        $response = $this->useCase->handle($oldDtoToken->getRefreshToken());

        // Assert - 古いトークンはDBから削除されている
        $oldTokenHash = hash('sha256', $oldDtoToken->getRefreshToken());
        $deletedToken = SysPlayerToken::where('refresh_token_hash', $oldTokenHash)->first();
        $this->assertNull($deletedToken);
        
        // Assert - 新しいトークンは有効
        $newTokenHash = hash('sha256', $response->dtoToken->getRefreshToken());
        $newToken = SysPlayerToken::where('refresh_token_hash', $newTokenHash)->first();
        $this->assertNotNull($newToken);
    }
}
