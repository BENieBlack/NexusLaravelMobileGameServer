<?php

namespace Tests\Unit\UseCases\Auth;

use App\Domain\Auth\Services\PlayerService;
use App\Domain\Auth\Services\TokenService;
use App\Domain\Auth\UseCases\SignInUseCase;
use App\Exceptions\GameException;
use App\Http\Responses\Auth\SignInResponse;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerTokenRepository;
use Illuminate\Support\Facades\Log;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class SignInUseCaseTest extends TestCase
{
    use RefreshMultipleDatabases;

    private SignInUseCase $useCase;
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
        $this->useCase = new SignInUseCase(
            $this->playerService,
            $this->tokenService
        );

        // Suppress log output during tests
        Log::spy();
    }

    /**
     * テスト用のプレイヤーとデバイスを作成するヘルパーメソッド
     */
    private function createPlayerAndDevice(string $deviceId, array $deviceInfo = []): array
    {
        $result = $this->playerService->createPlayer($deviceId, $deviceInfo);
        return [$result['sys_player'], $result['sys_player_device']];
    }

    /**
     * Test handle signs in existing player successfully
     */
    public function test_handle_signs_in_existing_player(): void
    {
        // Arrange
        $deviceId = 'existing-device-uuid-12345';
        $deviceInfo = ['model' => 'iPhone 14', 'os' => 'iOS 16.0'];
        
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice($deviceId, $deviceInfo);

        // Act
        $response = $this->useCase->handle($deviceId, $deviceInfo);

        // Assert
        $this->assertInstanceOf(SignInResponse::class, $response);
        $this->assertInstanceOf(SysPlayer::class, $response->sysPlayer);
        $this->assertInstanceOf(SysPlayerDevice::class, $response->sysPlayerDevice);
        
        // 同じプレイヤーとデバイスが返されることを確認
        $this->assertEquals($sysPlayer->id, $response->sysPlayer->id);
        $this->assertEquals($sysPlayerDevice->id, $response->sysPlayerDevice->id);
        $this->assertEquals($deviceId, $response->sysPlayerDevice->uuid);
    }

    /**
     * Test handle generates new token for existing player
     */
    public function test_handle_generates_new_token(): void
    {
        // Arrange
        $deviceId = 'device-for-new-token';
        $deviceInfo = ['model' => 'Test Device'];
        
        $this->createPlayerAndDevice($deviceId, $deviceInfo);

        // Act
        $response = $this->useCase->handle($deviceId, $deviceInfo);

        // Assert - 新しいトークンが生成されている
        $this->assertNotEmpty($response->dtoToken->accessToken);
        $this->assertNotEmpty($response->dtoToken->refreshToken);
        $this->assertEquals(3600, $response->dtoToken->expiresIn);

        // Assert - SysPlayerTokenが正しく生成されている
        $this->assertNotNull($response->sysPlayerToken->id);
        $this->assertNull($response->sysPlayerToken->revoked_at);
        $this->assertTrue($response->sysPlayerToken->isValid());
    }

    /**
     * Test handle throws exception when device not found
     */
    public function test_handle_throws_exception_when_device_not_found(): void
    {
        // Arrange
        $nonExistentDeviceId = 'non-existent-device-uuid';
        $deviceInfo = ['model' => 'Test'];

        // Assert & Act
        $this->expectException(GameException::class);
        $this->expectExceptionMessage("Device ID not found: {$nonExistentDeviceId}");

        $this->useCase->handle($nonExistentDeviceId, $deviceInfo);
    }

    /**
     * Test handle revokes old tokens before generating new one
     */
    public function test_handle_revokes_old_tokens(): void
    {
        // Arrange
        $deviceId = 'device-for-revoke-test';
        $deviceInfo = ['model' => 'Test'];
        
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice($deviceId, $deviceInfo);
        
        // 古いトークンを作成
        [$oldDtoToken1, $oldSysPlayerToken1] = $this->tokenService->generateToken($sysPlayer, $sysPlayerDevice);
        [$oldDtoToken2, $oldSysPlayerToken2] = $this->tokenService->generateToken($sysPlayer, $sysPlayerDevice);

        // 古いトークンが有効であることを確認
        $this->assertNotNull($this->tokenService->validateRefreshToken($oldDtoToken1->refreshToken));
        $this->assertNotNull($this->tokenService->validateRefreshToken($oldDtoToken2->refreshToken));

        // Act - サインインを実行
        $response = $this->useCase->handle($deviceId, $deviceInfo);

        // Assert - 古いトークンが無効化されている
        $this->assertNull($this->tokenService->validateRefreshToken($oldDtoToken1->refreshToken));
        $this->assertNull($this->tokenService->validateRefreshToken($oldDtoToken2->refreshToken));

        // Assert - 新しいトークンは有効
        $this->assertNotNull($this->tokenService->validateRefreshToken($response->dtoToken->refreshToken));
    }

    /**
     * Test handle updates last login time
     */
    public function test_handle_updates_last_login_time(): void
    {
        // Arrange
        $deviceId = 'device-for-last-login-test';
        $deviceInfo = ['model' => 'Test'];
        
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice($deviceId, $deviceInfo);
        $originalLastLogin = $sysPlayerDevice->last_login_at;

        // 時間を少し進める
        sleep(1);

        // Act
        $response = $this->useCase->handle($deviceId, $deviceInfo);

        // Assert - last_login_atが更新されている
        $updatedDevice = $this->playerService->findByDeviceId($deviceId);
        $this->assertNotNull($updatedDevice);
        $this->assertNotNull($updatedDevice->last_login_at);
        
        // 元の値と異なることを確認
        if ($originalLastLogin !== null) {
            $this->assertNotEquals(
                $originalLastLogin->format('Y-m-d H:i:s'),
                $updatedDevice->last_login_at->format('Y-m-d H:i:s')
            );
        }
    }

    /**
     * Test handle allows multiple sign-ins for same device
     */
    public function test_handle_allows_multiple_sign_ins(): void
    {
        // Arrange
        $deviceId = 'device-for-multiple-signin';
        $deviceInfo = ['model' => 'Test'];
        
        $this->createPlayerAndDevice($deviceId, $deviceInfo);

        // Act - 複数回サインイン
        $response1 = $this->useCase->handle($deviceId, $deviceInfo);
        $response2 = $this->useCase->handle($deviceId, $deviceInfo);
        $response3 = $this->useCase->handle($deviceId, $deviceInfo);

        // Assert - すべて同じプレイヤーとデバイス
        $this->assertEquals($response1->sysPlayer->id, $response2->sysPlayer->id);
        $this->assertEquals($response2->sysPlayer->id, $response3->sysPlayer->id);
        $this->assertEquals($response1->sysPlayerDevice->id, $response2->sysPlayerDevice->id);
        $this->assertEquals($response2->sysPlayerDevice->id, $response3->sysPlayerDevice->id);

        // Assert - トークンは異なる
        $this->assertNotEquals($response1->dtoToken->refreshToken, $response2->dtoToken->refreshToken);
        $this->assertNotEquals($response2->dtoToken->refreshToken, $response3->dtoToken->refreshToken);

        // Assert - 最新のトークンのみ有効
        $this->assertNull($this->tokenService->validateRefreshToken($response1->dtoToken->refreshToken));
        $this->assertNull($this->tokenService->validateRefreshToken($response2->dtoToken->refreshToken));
        $this->assertNotNull($this->tokenService->validateRefreshToken($response3->dtoToken->refreshToken));
    }

    /**
     * Test handle preserves player data
     */
    public function test_handle_preserves_player_data(): void
    {
        // Arrange
        $deviceId = 'device-for-preserve-test';
        $deviceInfo = ['model' => 'Original Device'];
        
        [$sysPlayer, $sysPlayerDevice] = $this->createPlayerAndDevice($deviceId, $deviceInfo);
        $originalMyId = $sysPlayer->my_id;
        $originalPlayerId = $sysPlayer->id;
        $originalUuid = $sysPlayer->uuid;

        // Act - 異なるデバイス情報でサインイン
        $newDeviceInfo = ['model' => 'New Device'];
        $response = $this->useCase->handle($deviceId, $newDeviceInfo);

        // Assert - プレイヤー情報は変わらない
        $this->assertEquals($originalPlayerId, $response->sysPlayer->id);
        $this->assertEquals($originalMyId, $response->sysPlayer->my_id);
        $this->assertEquals($originalUuid, $response->sysPlayer->uuid);
    }
}
