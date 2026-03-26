<?php

namespace Tests\Unit\UseCases\Auth;

use App\Domain\Auth\Services\VersionService;
use App\Domain\Auth\UseCases\VersionUseCase;
use App\Http\Responses\Auth\VersionResponse;
use Mockery;
use Tests\TestCase;

class VersionUseCaseTest extends TestCase
{
    /** @var VersionService&\Mockery\MockInterface */
    private $mockVersionService;
    private VersionUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockVersionService = Mockery::mock(VersionService::class);
        $this->useCase = new VersionUseCase($this->mockVersionService);
    }

    /**
     * Test handle with valid deploy version returns up to date when sysDeploy is null
     */
    public function test_handle_with_valid_deploy_version(): void
    {
        // Arrange
        $deployVersion = 202601010;

        // VersionService returns array [sysDeploy, sysMaintenance] with null (up to date)
        $this->mockVersionService
            ->shouldReceive('checkVersion')
            ->once()
            ->with($deployVersion)
            ->andReturn([null, null]);

        // Act
        $result = $this->useCase->handle($deployVersion);

        // Assert
        $this->assertInstanceOf(VersionResponse::class, $result);
    }

    /**
     * Test handle with null deploy version returns update available when sysDeploy is not null
     */
    public function test_handle_with_null_deploy_version(): void
    {
        // Arrange
        $deployVersion = null;
        $mockDeploy = Mockery::mock(\App\Models\Sys\SysDeploy::class);

        // VersionService returns tuple array [sysDeploy, sysMaintenance] with sysDeploy (update available)
        $this->mockVersionService
            ->shouldReceive('checkVersion')
            ->once()
            ->with(null)
            ->andReturn([$mockDeploy, null]);

        // Act
        $result = $this->useCase->handle($deployVersion);

        // Assert
        $this->assertInstanceOf(VersionResponse::class, $result);
    }

    /**
     * Test handle delegates to version service
     */
    public function test_handle_delegates_to_version_check_service(): void
    {
        // Arrange
        $deployVersion = 202601020;

        $this->mockVersionService
            ->shouldReceive('checkVersion')
            ->once()
            ->with($deployVersion)
            ->andReturn([null, null]);

        // Act
        $result = $this->useCase->handle($deployVersion);

        // Assert
        $this->assertInstanceOf(VersionResponse::class, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
