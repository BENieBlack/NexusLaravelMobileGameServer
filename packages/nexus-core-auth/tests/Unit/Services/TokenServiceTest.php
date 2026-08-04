<?php

namespace NexusAuth\Tests\Unit\Services;

use NexusAuth\Services\TokenService;
use NexusAuth\Contracts\TokenRepositoryInterface;
use NexusAuth\Contracts\PlayerModelInterface;
use NexusAuth\Contracts\DeviceModelInterface;
use NexusAuth\DTOs\TokenDto;
use PHPUnit\Framework\TestCase;

class TokenServiceTest extends TestCase
{
    private TokenRepositoryInterface $mockRepository;
    private TokenService $service;
    private string $appKey = 'test-app-key-for-signing-tokens';

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockRepository = $this->createMock(TokenRepositoryInterface::class);
        $this->service = new TokenService(
            $this->mockRepository,
            $this->appKey,
            3600, // 1 hour
            30    // 30 days
        );
    }

    public function test_generate_access_token_returns_valid_jwt_string(): void
    {
        $mockPlayer = $this->createMock(PlayerModelInterface::class);
        $mockPlayer->method('getId')->willReturn(100);
        $mockPlayer->method('getUuid')->willReturn('uuid-12345');

        $mockDevice = $this->createMock(DeviceModelInterface::class);
        $mockDevice->method('getId')->willReturn(1);

        $token = $this->service->generateAccessToken($mockPlayer, $mockDevice);

        // JWT should have 3 parts separated by dots
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
        
        // Each part should be base64 encoded
        $this->assertNotEmpty($parts[0]); // header
        $this->assertNotEmpty($parts[1]); // payload
        $this->assertNotEmpty($parts[2]); // signature
    }

    public function test_generate_refresh_token_returns_random_string(): void
    {
        $token = $this->service->generateRefreshToken();

        // Refresh token should be a non-empty string
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        
        // Should be reasonably long (at least 32 characters)
        $this->assertGreaterThanOrEqual(32, strlen($token));
    }

    public function test_generate_refresh_token_returns_unique_values(): void
    {
        $token1 = $this->service->generateRefreshToken();
        $token2 = $this->service->generateRefreshToken();

        $this->assertNotEquals($token1, $token2);
    }

    public function test_verify_access_token_returns_dto_for_valid_token(): void
    {
        $mockPlayer = $this->createMock(PlayerModelInterface::class);
        $mockPlayer->method('getId')->willReturn(100);
        $mockPlayer->method('getUuid')->willReturn('uuid-12345');

        $mockDevice = $this->createMock(DeviceModelInterface::class);
        $mockDevice->method('getId')->willReturn(1);

        $token = $this->service->generateAccessToken($mockPlayer, $mockDevice);

        $result = $this->service->verifyAccessToken($token);

        $this->assertInstanceOf(TokenDto::class, $result);
        $this->assertSame(100, $result->getPlayerId());
        $this->assertSame('uuid-12345', $result->getUuid());
        $this->assertSame(1, $result->getDeviceId());
    }

    public function test_verify_access_token_returns_null_for_invalid_format(): void
    {
        $invalidToken = 'not-a-valid-jwt-token';

        $result = $this->service->verifyAccessToken($invalidToken);

        $this->assertNull($result);
    }

    public function test_verify_access_token_returns_null_for_invalid_signature(): void
    {
        $mockPlayer = $this->createMock(PlayerModelInterface::class);
        $mockPlayer->method('getId')->willReturn(100);
        $mockPlayer->method('getUuid')->willReturn('uuid-12345');

        $mockDevice = $this->createMock(DeviceModelInterface::class);
        $mockDevice->method('getId')->willReturn(1);

        $token = $this->service->generateAccessToken($mockPlayer, $mockDevice);
        
        // Tamper with the signature
        $parts = explode('.', $token);
        $parts[2] = 'tampered-signature';
        $tamperedToken = implode('.', $parts);

        $result = $this->service->verifyAccessToken($tamperedToken);

        $this->assertNull($result);
    }

    public function test_verify_access_token_returns_null_for_expired_token(): void
    {
        // Create service with very short expiration
        $shortLivedService = new TokenService(
            $this->mockRepository,
            $this->appKey,
            -1, // Already expired
            30
        );

        $mockPlayer = $this->createMock(PlayerModelInterface::class);
        $mockPlayer->method('getId')->willReturn(100);
        $mockPlayer->method('getUuid')->willReturn('uuid-12345');

        $mockDevice = $this->createMock(DeviceModelInterface::class);
        $mockDevice->method('getId')->willReturn(1);

        $token = $shortLivedService->generateAccessToken($mockPlayer, $mockDevice);

        $result = $shortLivedService->verifyAccessToken($token);

        $this->assertNull($result);
    }
}
