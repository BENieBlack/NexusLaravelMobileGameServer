<?php

namespace Tests\Unit\UseCases\Auth;

use App\Domain\Auth\UseCases\RefreshTokenUseCase;
use App\Domain\Player\Services\PlayerService;
use App\Exceptions\GameException;
use App\Http\Responses\Auth\RefreshTokenResponse;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Sys\SysPlayerTokenRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use NexusAuth\Contracts\DeviceRepositoryInterface;
use NexusAuth\Contracts\PlayerRepositoryInterface;
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Services\TokenService;
use NexusUnitOfWork\Persistence\QueryManager;
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
            new SysPlayerRepository(new SysPlayer),
            new SysPlayerDeviceRepository(new SysPlayerDevice),
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
        $result = $this->playerService->createPlayer('test-device-'.uniqid(), ['model' => 'Test']);
        $sysPlayer = $result['sys_player'];
        $sysPlayerDevice = $result['sys_player_device'];

        [$token, $sysPlayerToken] = $this->tokenService->generateToken(
            $sysPlayer,
            $sysPlayerDevice,
            fn ($playerId, $deviceId, $tokenHash, $expiresAt) => SysPlayerToken::create([
                'sys_player_id' => $playerId,
                'sys_player_device_id' => $deviceId,
                'refresh_token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
            ])
        );

        // トークンをDBに保存（バッチINSERT）
        app(QueryManager::class)->execAllQuery();

        return [$sysPlayer, $sysPlayerDevice, $token, $sysPlayerToken];
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
        $response = $this->useCase->exec($oldDtoToken->getRefreshToken());

        // Assert
        $this->assertInstanceOf(RefreshTokenResponse::class, $response);
        $this->assertNotNull($response->token);
        $this->assertNotEmpty($response->token->getAccessToken());
        $this->assertNotEmpty($response->token->getRefreshToken());
        $this->assertEquals(3600, $response->token->getExpiresIn());
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
        $response = $this->useCase->exec($oldDtoToken->getRefreshToken());

        // Assert - 新しいトークンは古いトークンと異なる
        $this->assertNotEquals($oldDtoToken->getRefreshToken(), $response->token->getRefreshToken());
        $this->assertNotEquals($oldDtoToken->getAccessToken(), $response->token->getAccessToken());
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
        $response = $this->useCase->exec($oldDtoToken->getRefreshToken());

        // Assert - 古いトークンはDBから削除されている
        $tokenHash = hash('sha256', $oldDtoToken->getRefreshToken());
        $deletedToken = SysPlayerToken::where('refresh_token_hash', $tokenHash)->first();
        $this->assertNull($deletedToken);

        // Assert - 新しいトークンは有効
        $this->assertNotNull($this->tokenService->validateRefreshToken($response->token->getRefreshToken()));
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

        $this->useCase->exec($invalidRefreshToken);
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
        SysPlayerToken::where('id', $oldSysPlayerToken->id)
            ->update(['revoked_at' => $oldSysPlayerToken->revoked_at]);

        // Assert & Act
        $this->expectException(GameException::class);
        $this->expectExceptionMessage('Invalid or expired refresh token');

        $this->useCase->exec($oldDtoToken->getRefreshToken());
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
        SysPlayerToken::where('id', $oldSysPlayerToken->id)
            ->update(['expires_at' => $oldSysPlayerToken->expires_at]);

        // Assert & Act
        $this->expectException(GameException::class);
        $this->expectExceptionMessage('Invalid or expired refresh token');

        $this->useCase->exec($oldDtoToken->getRefreshToken());
    }

    /**
     * Test handle allows multiple token refreshes in sequence
     */
    public function test_handle_allows_multiple_token_refreshes(): void
    {
        // Arrange
        [, , $tokenDto1, $sysPlayerToken1] = $this->createPlayerDeviceAndToken();
        $sysPlayerToken1->load(['player', 'device']);

        sleep(1);

        // Act - 1回目のリフレッシュ
        $response1 = $this->useCase->exec($tokenDto1->getRefreshToken());

        sleep(1);

        // Act - 2回目のリフレッシュ（1回目で得たトークンを使用）
        $response2 = $this->useCase->exec($response1->token->getRefreshToken());

        sleep(1);

        // Act - 3回目のリフレッシュ（2回目で得たトークンを使用）
        $response3 = $this->useCase->exec($response2->token->getRefreshToken());

        // Assert - すべて異なるトークンが返される
        $this->assertNotEquals($tokenDto1->getRefreshToken(), $response1->token->getRefreshToken());
        $this->assertNotEquals($response1->token->getRefreshToken(), $response2->token->getRefreshToken());
        $this->assertNotEquals($response2->token->getRefreshToken(), $response3->token->getRefreshToken());

        // Assert - 古いトークンはDBから削除されている
        $tokenHash1 = hash('sha256', $tokenDto1->getRefreshToken());
        $tokenHash2 = hash('sha256', $response1->token->getRefreshToken());
        $tokenHash3 = hash('sha256', $response2->token->getRefreshToken());

        $this->assertNull(SysPlayerToken::where('refresh_token_hash', $tokenHash1)->first());
        $this->assertNull(SysPlayerToken::where('refresh_token_hash', $tokenHash2)->first());
        $this->assertNull(SysPlayerToken::where('refresh_token_hash', $tokenHash3)->first());

        // Assert - 最新のトークンのみ有効
        $this->assertNotNull($this->tokenService->validateRefreshToken($response3->token->getRefreshToken()));
    }

    /**
     * Test handle updates last login time via token rotation
     */
    public function test_handle_updates_last_login_time(): void
    {
        // Arrange
        [$sysPlayer, $sysPlayerDevice, $token, $sysPlayerToken] = $this->createPlayerDeviceAndToken();
        $sysPlayerToken->load(['player', 'device']);
        $originalLastLoginAtString = $sysPlayerDevice->getLastLoginAt();
        $originalLastLoginAt = $originalLastLoginAtString !== null ? Carbon::parse($originalLastLoginAtString) : null;

        // 時間を進める
        sleep(2);

        // Act
        $this->useCase->exec($token->getRefreshToken());

        // Assert - last_login_atが更新されている
        $updatedDevice = $this->playerService->selectByDeviceId($sysPlayerDevice->getUuid());
        $this->assertNotNull($updatedDevice);
        $updatedLastLoginAtString = $updatedDevice->getLastLoginAt();
        $this->assertNotNull($updatedLastLoginAtString);

        // 更新されたことを確認（元の値がnullでない場合は異なる値になっているはず）
        if ($originalLastLoginAt !== null) {
            // タイムスタンプが同じか後であることを確認
            $updatedLastLoginAt = Carbon::parse($updatedLastLoginAtString);
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
        $response = $this->useCase->exec($oldDtoToken->getRefreshToken());

        // Assert - 古いトークンはDBから削除されている
        $oldTokenHash = hash('sha256', $oldDtoToken->getRefreshToken());
        $deletedToken = SysPlayerToken::where('refresh_token_hash', $oldTokenHash)->first();
        $this->assertNull($deletedToken);

        // Assert - 新しいトークンは有効
        $newTokenHash = hash('sha256', $response->token->getRefreshToken());
        $newToken = SysPlayerToken::where('refresh_token_hash', $newTokenHash)->first();
        $this->assertNotNull($newToken);
    }
}
