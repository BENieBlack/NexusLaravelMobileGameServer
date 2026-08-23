<?php

namespace Tests\Unit\UseCases\Auth;

use App\Domain\Auth\UseCases\SignInUseCase;
use App\Exceptions\GameException;
use App\Http\Responses\Auth\SignInResponse;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Repositories\Sys\SysPlayerDeviceRepository;
use App\Repositories\Sys\SysPlayerRepository;
use Mockery;
use NexusAuth\Contracts\TokenRepositoryInterface;
use NexusAuth\Services\PlayerAuthService;
use NexusAuth\Services\TokenService;
use NexusAuth\ValueObjects\Token;
use Tests\TestCase;

class SignInUseCaseUnitTest extends TestCase
{
    private SignInUseCase $useCase;

    private PlayerAuthService $playerAuthService;

    private TokenService $tokenService;

    private SysPlayerRepository $playerRepository;

    private SysPlayerDeviceRepository $deviceRepository;

    private TokenRepositoryInterface $tokenRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock repositories
        $this->playerRepository = Mockery::mock(SysPlayerRepository::class);
        $this->deviceRepository = Mockery::mock(SysPlayerDeviceRepository::class);
        $this->tokenRepository = Mockery::mock(TokenRepositoryInterface::class);

        // Mock services
        $this->playerAuthService = Mockery::mock(PlayerAuthService::class);
        $this->tokenService = Mockery::mock(TokenService::class);

        // Create UseCase with mocked dependencies
        $this->useCase = new SignInUseCase(
            $this->playerAuthService,
            $this->tokenService,
            $this->deviceRepository,
            $this->playerRepository,
            $this->tokenRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test handle signs in existing player successfully
     */
    public function test_handle_signs_in_existing_player_successfully(): void
    {
        // Arrange
        $deviceId = 'test-device-uuid';
        $deviceInfo = ['model' => 'iPhone 14'];

        $mockDevice = new SysPlayerDevice;
        $mockDevice->id = 10;
        $mockDevice->sys_player_id = 1;
        $mockDevice->uuid = $deviceId;
        $mockDevice->exists = true;

        $mockPlayer = new SysPlayer;
        $mockPlayer->id = 1;
        $mockPlayer->uuid = 'player-uuid-123';
        $mockPlayer->exists = true;

        $mockToken = new SysPlayerToken;
        $mockToken->id = 100;
        $mockToken->sys_player_id = 1;
        $mockToken->refresh_token_hash = hash('sha256', 'refresh-token-456');
        $mockToken->expires_at = now()->addDays(30);
        $mockToken->exists = true;

        $token = new Token(
            accessToken: 'access-token-123',
            refreshToken: 'refresh-token-456',
            expiresIn: 3600
        );

        // Mock expectations
        $this->deviceRepository
            ->shouldReceive('selectByDeviceId')
            ->with($deviceId)
            ->andReturn($mockDevice);

        $this->playerRepository
            ->shouldReceive('selectById')
            ->with(1)
            ->andReturn($mockPlayer);

        $this->tokenRepository
            ->shouldReceive('deleteByPlayerId')
            ->with(1)
            ->andReturn(2);

        $this->tokenService
            ->shouldReceive('generateToken')
            ->with($mockPlayer, $mockDevice, Mockery::type('callable'))
            ->andReturn([$token, $mockToken]);

        $this->playerAuthService
            ->shouldReceive('updateLastLogin')
            ->with($deviceId)
            ->andReturn(true);

        $this->tokenRepository
            ->shouldReceive('selectByRefreshToken')
            ->with('refresh-token-456')
            ->andReturn($mockToken);

        // Act
        $response = $this->useCase->exec($deviceId, $deviceInfo);

        // Assert
        $this->assertInstanceOf(SignInResponse::class, $response);
        $this->assertEquals($mockPlayer, $response->sysPlayer);
        $this->assertEquals($mockDevice, $response->sysPlayerDevice);
        $this->assertEquals($mockToken, $response->sysPlayerToken);
        $this->assertEquals($token, $response->token);
    }

    /**
     * Test handle throws exception when device not found
     */
    public function test_handle_throws_exception_when_device_not_found(): void
    {
        // Arrange
        $deviceId = 'non-existent-device';
        $deviceInfo = ['model' => 'Test'];

        $this->deviceRepository
            ->shouldReceive('selectByDeviceId')
            ->with($deviceId)
            ->andReturn(null);

        // Assert & Act
        $this->expectException(GameException::class);
        $this->expectExceptionMessage("Device ID not found: {$deviceId}");

        $this->useCase->exec($deviceId, $deviceInfo);
    }

    /**
     * Test handle throws exception when player not found
     */
    public function test_handle_throws_exception_when_player_not_found(): void
    {
        // Arrange
        $deviceId = 'test-device';
        $deviceInfo = ['model' => 'Test'];

        $mockDevice = new SysPlayerDevice;
        $mockDevice->id = 10;
        $mockDevice->sys_player_id = 999;
        $mockDevice->exists = true;

        $this->deviceRepository
            ->shouldReceive('selectByDeviceId')
            ->with($deviceId)
            ->andReturn($mockDevice);

        $this->playerRepository
            ->shouldReceive('selectById')
            ->with(999)
            ->andReturn(null);

        // Assert & Act
        $this->expectException(GameException::class);
        $this->expectExceptionMessage("Player not found for device: {$deviceId}");

        $this->useCase->exec($deviceId, $deviceInfo);
    }

    /**
     * Test handle deletes old tokens before generating new one
     */
    public function test_handle_deletes_old_tokens(): void
    {
        // Arrange
        $deviceId = 'test-device';
        $deviceInfo = ['model' => 'Test'];

        $mockDevice = new SysPlayerDevice;
        $mockDevice->id = 10;
        $mockDevice->sys_player_id = 1;
        $mockDevice->uuid = $deviceId;
        $mockDevice->exists = true;

        $mockPlayer = new SysPlayer;
        $mockPlayer->id = 1;
        $mockPlayer->exists = true;

        $mockToken = new SysPlayerToken;
        $mockToken->id = 100;
        $mockToken->sys_player_id = 1;
        $mockToken->exists = true;

        $token = new Token(
            accessToken: 'new-access-token',
            refreshToken: 'new-refresh-token',
            expiresIn: 3600
        );

        $this->deviceRepository
            ->shouldReceive('selectByDeviceId')
            ->andReturn($mockDevice);

        $this->playerRepository
            ->shouldReceive('selectById')
            ->andReturn($mockPlayer);

        // Expect deleteByPlayerId to be called
        $this->tokenRepository
            ->shouldReceive('deleteByPlayerId')
            ->with(1)
            ->once()
            ->andReturn(3); // 3 tokens deleted

        $this->tokenService
            ->shouldReceive('generateToken')
            ->with($mockPlayer, $mockDevice, Mockery::type('callable'))
            ->andReturn([$token, $mockToken]);

        $this->playerAuthService
            ->shouldReceive('updateLastLogin')
            ->andReturn(true);

        $this->tokenRepository
            ->shouldReceive('selectByRefreshToken')
            ->andReturn($mockToken);

        // Act
        $response = $this->useCase->exec($deviceId, $deviceInfo);

        // Assert - Mock expectations are verified automatically by Mockery
        $this->assertInstanceOf(SignInResponse::class, $response);
    }

    /**
     * Test handle updates last login time
     */
    public function test_handle_updates_last_login_time(): void
    {
        // Arrange
        $deviceId = 'test-device';
        $deviceInfo = ['model' => 'Test'];

        $mockDevice = new SysPlayerDevice;
        $mockDevice->id = 10;
        $mockDevice->sys_player_id = 1;
        $mockDevice->uuid = $deviceId;
        $mockDevice->exists = true;

        $mockPlayer = new SysPlayer;
        $mockPlayer->id = 1;
        $mockPlayer->exists = true;

        $mockToken = new SysPlayerToken;
        $mockToken->id = 100;
        $mockToken->exists = true;

        $token = new Token(
            accessToken: 'access-token',
            refreshToken: 'refresh-token',
            expiresIn: 3600
        );

        $this->deviceRepository->shouldReceive('selectByDeviceId')->andReturn($mockDevice);
        $this->playerRepository->shouldReceive('selectById')->andReturn($mockPlayer);
        $this->tokenRepository->shouldReceive('deleteByPlayerId')->andReturn(0);
        $this->tokenService->shouldReceive('generateToken')->with($mockPlayer, $mockDevice, Mockery::type('callable'))->andReturn([$token, $mockToken]);
        $this->tokenRepository->shouldReceive('selectByRefreshToken')->andReturn($mockToken);

        // Expect updateLastLogin to be called with deviceId
        $this->playerAuthService
            ->shouldReceive('updateLastLogin')
            ->with($deviceId)
            ->once()
            ->andReturn(true);

        // Act
        $response = $this->useCase->exec($deviceId, $deviceInfo);

        // Assert - Mock expectations are verified automatically
        $this->assertInstanceOf(SignInResponse::class, $response);
    }
}
