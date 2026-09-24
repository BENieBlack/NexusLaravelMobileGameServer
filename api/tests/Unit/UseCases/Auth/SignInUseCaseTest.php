<?php

namespace Tests\Unit\UseCases\Auth;

use App\Domain\Auth\UseCases\SignInUseCase;
use App\Exceptions\GameException;
use App\Http\Responses\Auth\SignInResponse;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Repositories\Sys\SysPlayerRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Nexus\Core\Repositories\PlayerDeviceRepositoryInterface;
use NexusAuth\Contracts\TokenRepositoryInterface;
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Services\TokenService;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class SignInUseCaseTest extends TestCase
{
    use RefreshMultipleDatabases;

    private SignInUseCase $useCase;

    private PlayerAuthService $playerAuthService;

    private TokenService $tokenService;

    private SysPlayerRepository $playerRepository;

    private PlayerDeviceRepositoryInterface $deviceRepository;

    private TokenRepositoryInterface $tokenRepository;

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
        $this->playerRepository = app(SysPlayerRepository::class);
        $this->deviceRepository = app(PlayerDeviceRepositoryInterface::class);
        $this->tokenRepository = app(TokenRepositoryInterface::class);

        // Servicesを作成
        $this->playerAuthService = app(PlayerAuthService::class);
        $this->tokenService = app(TokenService::class);

        // UseCaseを作成
        $this->useCase = new SignInUseCase(
            $this->playerAuthService,
            $this->tokenService,
            $this->deviceRepository,
            $this->playerRepository,
            $this->tokenRepository
        );

        // Suppress log output during tests
        Log::spy();
    }

    /**
     * テスト用のプレイヤーとデバイスを作成するヘルパーメソッド
     */
    private function createPlayerAndDevice(string $deviceId, array $deviceInfo = []): array
    {
        // プレイヤーを作成
        $player = $this->playerAuthService->createPlayer($deviceId, $deviceInfo);

        // デバイスを作成
        $device = SysPlayerDevice::create([
            'sys_player_id' => $player->getId(),
            'uuid' => $deviceId,
            'device_info' => $deviceInfo,
            'last_login_at' => now(),
        ]);

        return [$player, $device];
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
        $response = $this->useCase->exec($deviceId, $deviceInfo);

        // Assert
        $this->assertInstanceOf(SignInResponse::class, $response);
        $this->assertInstanceOf(SysPlayer::class, $response->sysPlayer);
        $this->assertInstanceOf(SysPlayerDevice::class, $response->sysPlayerDevice);

        // 同じプレイヤーとデバイスが返されることを確認
        $this->assertEquals($sysPlayer->getId(), $response->sysPlayer->getId());
        $this->assertEquals($sysPlayerDevice->getId(), $response->sysPlayerDevice->getId());
        $this->assertEquals($deviceId, $response->sysPlayerDevice->getUuid());
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
        $response = $this->useCase->exec($deviceId, $deviceInfo);

        // Assert - 新しいトークンが生成されている
        $this->assertNotEmpty($response->token->getAccessToken());
        $this->assertNotEmpty($response->token->getRefreshToken());
        $this->assertEquals(3600, $response->token->getExpiresIn());

        // Assert - SysPlayerTokenが正しく生成されている
        $this->assertInstanceOf(SysPlayerToken::class, $response->sysPlayerToken);
        $this->assertFalse($response->sysPlayerToken->isExpired());
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

        $this->useCase->exec($nonExistentDeviceId, $deviceInfo);
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

        // 2回サインインして古いトークンを作成
        $response1 = $this->useCase->exec($deviceId, $deviceInfo);
        $response2 = $this->useCase->exec($deviceId, $deviceInfo);

        // Act - 3回目のサインインを実行
        $response3 = $this->useCase->exec($deviceId, $deviceInfo);

        // Assert - プレイヤーのトークンは最新の1つのみ
        $allTokens = SysPlayerToken::where('sys_player_id', $sysPlayer->getId())->get();
        $this->assertCount(1, $allTokens);
        $this->assertEquals($response3->sysPlayerToken->getId(), $allTokens->first()->getId());
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
        $originalLastLoginString = $sysPlayerDevice->getLastLoginAt();
        $originalLastLogin = $originalLastLoginString !== null ? Carbon::parse($originalLastLoginString) : null;

        // 時間を少し進める
        sleep(1);

        // Act
        $response = $this->useCase->exec($deviceId, $deviceInfo);

        // Assert - last_login_atが更新されている
        $updatedDevice = $this->deviceRepository->selectByDeviceId($deviceId);
        $this->assertNotNull($updatedDevice);
        $this->assertNotNull($updatedDevice->getLastLoginAt());

        // 元の値と異なることを確認（タイムスタンプが同じか後であることを確認）
        if ($originalLastLogin !== null) {
            $updatedLastLoginString = $updatedDevice->getLastLoginAt();
            $updatedLastLogin = Carbon::parse($updatedLastLoginString);
            $this->assertGreaterThanOrEqual(
                $originalLastLogin->getTimestamp(),
                $updatedLastLogin->getTimestamp()
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
        $response1 = $this->useCase->exec($deviceId, $deviceInfo);
        $response2 = $this->useCase->exec($deviceId, $deviceInfo);
        $response3 = $this->useCase->exec($deviceId, $deviceInfo);

        // Assert - すべて同じプレイヤーとデバイス
        $this->assertEquals($response1->sysPlayer->getId(), $response2->sysPlayer->getId());
        $this->assertEquals($response2->sysPlayer->getId(), $response3->sysPlayer->getId());
        $this->assertEquals($response1->sysPlayerDevice->getId(), $response2->sysPlayerDevice->getId());
        $this->assertEquals($response2->sysPlayerDevice->getId(), $response3->sysPlayerDevice->getId());

        // Assert - トークンは異なる
        $this->assertNotEquals($response1->token->getRefreshToken(), $response2->token->getRefreshToken());
        $this->assertNotEquals($response2->token->getRefreshToken(), $response3->token->getRefreshToken());

        // Assert - 最新のトークンのみDBに存在する
        $allTokens = SysPlayerToken::where('sys_player_id', $response1->sysPlayer->getId())->get();
        $this->assertCount(1, $allTokens);
        $this->assertEquals($response3->sysPlayerToken->getId(), $allTokens->first()->getId());
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
        $originalMyId = $sysPlayer->getMyId();
        $originalPlayerId = $sysPlayer->getId();
        $originalUuid = $sysPlayer->getUuid();

        // Act - 異なるデバイス情報でサインイン
        $newDeviceInfo = ['model' => 'New Device'];
        $response = $this->useCase->exec($deviceId, $newDeviceInfo);

        // Assert - プレイヤー情報は変わらない
        $this->assertEquals($originalPlayerId, $response->sysPlayer->getId());
        $this->assertEquals($originalMyId, $response->sysPlayer->getMyId());
        $this->assertEquals($originalUuid, $response->sysPlayer->getUuid());
    }
}
