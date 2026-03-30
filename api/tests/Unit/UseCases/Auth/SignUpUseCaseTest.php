<?php

namespace Tests\Unit\UseCases\Auth;

use App\Domain\Auth\Services\PlayerService;
use App\Domain\Auth\Services\TokenService;
use App\Domain\Auth\UseCases\SignUpUseCase;
use App\Exceptions\BusinessLogicException;
use App\Http\Responses\Auth\SignUpResponse;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerTokenRepository;
use Illuminate\Support\Facades\Log;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class SignUpUseCaseTest extends TestCase
{
    use RefreshMultipleDatabases;

    private SignUpUseCase $useCase;
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
        $this->useCase = new SignUpUseCase(
            $this->playerService,
            $this->tokenService
        );

        // Suppress log output during tests
        Log::spy();
    }

    /**
     * Test handle creates new player successfully
     */
    public function test_handle_creates_new_player_successfully(): void
    {
        // Arrange
        $deviceId = 'new-device-uuid-12345';
        $deviceInfo = [
            'model' => 'iPhone 14',
            'os' => 'iOS 16.0',
            'app_version' => '1.0.0',
        ];

        // Act
        $response = $this->useCase->handle($deviceId, $deviceInfo);

        // Assert
        $this->assertInstanceOf(SignUpResponse::class, $response);
        $this->assertInstanceOf(SysPlayer::class, $response->sysPlayer);
        $this->assertInstanceOf(SysPlayerDevice::class, $response->sysPlayerDevice);
        $this->assertNotNull($response->sysPlayer->id);
        $this->assertNotNull($response->sysPlayerDevice->id);
        $this->assertEquals($deviceId, $response->sysPlayerDevice->uuid);
        $this->assertEquals($deviceInfo, $response->sysPlayerDevice->device_info);

        // データベースに保存されていることを確認
        $this->assertDatabaseHas('sys_player', [
            'id' => $response->sysPlayer->id,
        ], 'sys');

        $this->assertDatabaseHas('sys_player_device', [
            'id' => $response->sysPlayerDevice->id,
            'uuid' => $deviceId,
        ], 'sys');

        $this->assertDatabaseHas('sys_player_token', [
            'sys_player_id' => $response->sysPlayer->id,
            'sys_player_device_id' => $response->sysPlayerDevice->id,
        ], 'sys');
    }

    /**
     * Test handle creates token with player info
     */
    public function test_handle_creates_token_with_player_info(): void
    {
        // Arrange
        $deviceId = 'new-device-uuid-67890';
        $deviceInfo = ['model' => 'Test Device'];

        // Act
        $response = $this->useCase->handle($deviceId, $deviceInfo);

        // Assert - DtoTokenが正しく生成されている
        $this->assertNotEmpty($response->dtoToken->accessToken);
        $this->assertNotEmpty($response->dtoToken->refreshToken);
        $this->assertEquals(3600, $response->dtoToken->expiresIn);

        // Assert - SysPlayerTokenが正しく生成されている
        $this->assertInstanceOf(SysPlayerToken::class, $response->sysPlayerToken);
        $this->assertEquals($response->sysPlayer->id, $response->sysPlayerToken->sys_player_id);
        $this->assertEquals($response->sysPlayerDevice->id, $response->sysPlayerToken->sys_player_device_id);
        $this->assertNull($response->sysPlayerToken->revoked_at);
        $this->assertTrue($response->sysPlayerToken->isValid());
    }

    /**
     * Test handle throws exception when device already exists
     */
    public function test_handle_throws_exception_when_device_exists(): void
    {
        // Arrange - 既存のデバイスを作成
        $deviceId = 'existing-device-uuid';
        $deviceInfo = ['model' => 'Test Device'];
        
        // 既存のプレイヤーとデバイスを作成
        $this->playerService->createPlayer($deviceId, $deviceInfo);

        // Assert & Act
        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage("Device ID already registered: {$deviceId}");

        // 同じデバイスIDでサインアップを試みる
        $this->useCase->handle($deviceId, $deviceInfo);
    }

    /**
     * Test handle creates unique players for different devices
     */
    public function test_handle_creates_unique_players_for_different_devices(): void
    {
        // Arrange
        $deviceId1 = 'device-uuid-1';
        $deviceId2 = 'device-uuid-2';
        $deviceInfo = ['model' => 'Test Device'];

        // Act
        $response1 = $this->useCase->handle($deviceId1, $deviceInfo);
        $response2 = $this->useCase->handle($deviceId2, $deviceInfo);

        // Assert - 異なるプレイヤーが作成されている
        $this->assertNotEquals($response1->sysPlayer->id, $response2->sysPlayer->id);
        $this->assertNotEquals($response1->sysPlayer->my_id, $response2->sysPlayer->my_id);
        $this->assertNotEquals($response1->sysPlayerDevice->id, $response2->sysPlayerDevice->id);
        $this->assertNotEquals($response1->sysPlayerToken->refresh_token_hash, $response2->sysPlayerToken->refresh_token_hash);

        // Assert - それぞれのデバイスIDが正しく設定されている
        $this->assertEquals($deviceId1, $response1->sysPlayerDevice->uuid);
        $this->assertEquals($deviceId2, $response2->sysPlayerDevice->uuid);
    }

    /**
     * Test handle creates player without device info
     */
    public function test_handle_creates_player_without_device_info(): void
    {
        // Arrange
        $deviceId = 'device-without-info';
        $deviceInfo = [];

        // Act
        $response = $this->useCase->handle($deviceId, $deviceInfo);

        // Assert
        $this->assertInstanceOf(SignUpResponse::class, $response);
        $this->assertNotNull($response->sysPlayer->id);
        $this->assertNotNull($response->sysPlayerDevice->id);
        $this->assertEquals($deviceId, $response->sysPlayerDevice->uuid);
        
        // device_infoは空配列として保存される
        $this->assertEquals([], $response->sysPlayerDevice->device_info);
    }

    /**
     * Test handle generates valid my_id
     */
    public function test_handle_generates_valid_my_id(): void
    {
        // Arrange
        $deviceId = 'device-for-my-id-test';
        $deviceInfo = ['model' => 'Test'];

        // Act
        $response = $this->useCase->handle($deviceId, $deviceInfo);

        // Assert - my_idが8文字の英数字であることを確認
        $this->assertNotNull($response->sysPlayer->my_id);
        $this->assertEquals(8, strlen($response->sysPlayer->my_id));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{8}$/', $response->sysPlayer->my_id);
    }

    /**
     * Test handle rollback on error
     */
    public function test_handle_rollback_on_error(): void
    {
        // Arrange
        $deviceId = 'device-for-rollback-test';
        $deviceInfo = ['model' => 'Test'];

        // 最初のサインアップを成功させる
        $this->useCase->handle($deviceId, $deviceInfo);

        // データベースに保存されていることを確認
        $this->assertDatabaseHas('sys_player_device', [
            'uuid' => $deviceId,
        ], 'sys');

        // Act & Assert - 同じデバイスIDで再度サインアップを試みる（エラーになる）
        try {
            $this->useCase->handle($deviceId, $deviceInfo);
            $this->fail('Expected BusinessLogicException was not thrown');
        } catch (BusinessLogicException $e) {
            // 例外が発生することを確認
            $this->assertStringContainsString("already registered", $e->getMessage());
        }

        // Assert - データベースには1つのデバイスのみ存在することを確認（ロールバック成功）
        $count = SysPlayerDevice::where('uuid', $deviceId)->count();
        $this->assertEquals(1, $count);
    }
}
