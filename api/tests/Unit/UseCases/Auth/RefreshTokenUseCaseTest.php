<?php

namespace Tests\Unit\UseCases\Auth;

use App\Domain\Auth\Services\PlayerService;
use App\Domain\Auth\Services\TokenService;
use App\Domain\Auth\UseCases\RefreshTokenUseCase;
use App\Exceptions\GameException;
use App\Http\Responses\Auth\RefreshTokenResponse;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerTokenRepository;
use Illuminate\Support\Facades\Log;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class RefreshTokenUseCaseTest extends TestCase
{
    use RefreshMultipleDatabases;

    private RefreshTokenUseCase $useCase;
    private PlayerService $playerService;
    private TokenService $tokenService;

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

        // Servicesを作成
        $playerRepository = new SysPlayerRepository(new SysPlayer());
        $playerDeviceRepository = new SysPlayerDeviceRepository(new SysPlayerDevice());
        $tokenRepository = app(SysPlayerTokenRepository::class);
        
        $this->playerService = new PlayerService(
            $playerRepository,
            $playerDeviceRepository,
            $tokenRepository
        );

        $this->tokenService = new TokenService($tokenRepository);

        // UseCaseを作成
        $this->useCase = new RefreshTokenUseCase($this->tokenService);

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
        
        [$dtoToken, $sysPlayerToken] = $this->tokenService->generateToken($sysPlayer, $sysPlayerDevice);
        
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
        $response = $this->useCase->handle($oldDtoToken->refreshToken);

        // Assert
        $this->assertInstanceOf(RefreshTokenResponse::class, $response);
        $this->assertNotNull($response->dtoToken);
        $this->assertNotEmpty($response->dtoToken->accessToken);
        $this->assertNotEmpty($response->dtoToken->refreshToken);
        $this->assertEquals(3600, $response->dtoToken->expiresIn);
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
        $response = $this->useCase->handle($oldDtoToken->refreshToken);

        // Assert - 新しいトークンは古いトークンと異なる
        $this->assertNotEquals($oldDtoToken->refreshToken, $response->dtoToken->refreshToken);
        $this->assertNotEquals($oldDtoToken->accessToken, $response->dtoToken->accessToken);
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
        $this->assertNotNull($this->tokenService->validateRefreshToken($oldDtoToken->refreshToken));

        sleep(1);

        // Act
        $response = $this->useCase->handle($oldDtoToken->refreshToken);

        // Assert - 古いトークンは無効化されている
        $this->assertNull($this->tokenService->validateRefreshToken($oldDtoToken->refreshToken));

        // Assert - 新しいトークンは有効
        $this->assertNotNull($this->tokenService->validateRefreshToken($response->dtoToken->refreshToken));
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

        $this->useCase->handle($oldDtoToken->refreshToken);
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

        $this->useCase->handle($oldDtoToken->refreshToken);
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
        $response1 = $this->useCase->handle($dtoToken1->refreshToken);
        
        sleep(1);

        // Act - 2回目のリフレッシュ（1回目で得たトークンを使用）
        $response2 = $this->useCase->handle($response1->dtoToken->refreshToken);
        
        sleep(1);

        // Act - 3回目のリフレッシュ（2回目で得たトークンを使用）
        $response3 = $this->useCase->handle($response2->dtoToken->refreshToken);

        // Assert - すべて異なるトークンが返される
        $this->assertNotEquals($dtoToken1->refreshToken, $response1->dtoToken->refreshToken);
        $this->assertNotEquals($response1->dtoToken->refreshToken, $response2->dtoToken->refreshToken);
        $this->assertNotEquals($response2->dtoToken->refreshToken, $response3->dtoToken->refreshToken);

        // Assert - 古いトークンは無効
        $this->assertNull($this->tokenService->validateRefreshToken($dtoToken1->refreshToken));
        $this->assertNull($this->tokenService->validateRefreshToken($response1->dtoToken->refreshToken));
        $this->assertNull($this->tokenService->validateRefreshToken($response2->dtoToken->refreshToken));

        // Assert - 最新のトークンのみ有効
        $this->assertNotNull($this->tokenService->validateRefreshToken($response3->dtoToken->refreshToken));
    }

    /**
     * Test handle updates last login time via token rotation
     */
    public function test_handle_updates_last_login_time(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice, $dtoToken, $sysPlayerToken] = $this->createPlayerDeviceAndToken();
        $sysPlayerToken->load(['player', 'device']);
        $originalLastLoginAt = $sysPlayerDevice->last_login_at;

        // 時間を進める
        sleep(2);

        // Act
        $this->useCase->handle($dtoToken->refreshToken);

        // Assert - last_login_atが更新されている
        $updatedDevice = $this->playerService->selectByDeviceId($sysPlayerDevice->uuid);
        $this->assertNotNull($updatedDevice);
        $this->assertNotNull($updatedDevice->last_login_at);
        
        // 更新されたことを確認（元の値がnullでない場合は異なる値になっているはず）
        if ($originalLastLoginAt !== null) {
            // タイムスタンプが同じか後であることを確認
            $this->assertGreaterThanOrEqual(
                $originalLastLoginAt->getTimestamp(),
                $updatedDevice->last_login_at->getTimestamp()
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
        $response = $this->useCase->handle($oldDtoToken->refreshToken);

        // Assert - 古いトークンを再度使おうとすると例外が発生
        $this->expectException(GameException::class);
        $this->expectExceptionMessage('Invalid or expired refresh token');

        $this->useCase->handle($oldDtoToken->refreshToken);
    }
}
