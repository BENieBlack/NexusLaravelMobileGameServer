<?php

namespace NexusVersion\Tests\Unit\Services;

use Mockery;
use NexusVersion\Repositories\DeployRepositoryInterface;
use NexusVersion\Repositories\MaintenanceRepositoryInterface;
use NexusVersion\Services\VersionService;
use PHPUnit\Framework\TestCase;

class VersionServiceTest extends TestCase
{
    private VersionService $service;

    private DeployRepositoryInterface $deployRepository;

    private MaintenanceRepositoryInterface $maintenanceRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deployRepository = Mockery::mock(DeployRepositoryInterface::class);
        $this->maintenanceRepository = Mockery::mock(MaintenanceRepositoryInterface::class);

        $this->service = new VersionService(
            $this->deployRepository,
            $this->maintenanceRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * クライアントが最新バージョンの場合、deployはnullを返す
     */
    public function test_check_version_returns_null_deploy_when_client_is_latest(): void
    {
        // Arrange
        $currentDeployId = 100;

        $latestDeploy = [
            'id' => 100,
            'deploy_key' => 202601010,
            'start_at' => '2026-01-15 10:00:00',
            'is_active' => true,
        ];

        $this->maintenanceRepository->shouldReceive('selectCurrent')
            ->once()
            ->andReturn(null);

        $this->deployRepository->shouldReceive('selectLatestDownloadable')
            ->once()
            ->andReturn($latestDeploy);

        // Act
        $result = $this->service->checkVersion($currentDeployId);

        // Assert
        $this->assertNull($result['deploy']);
        $this->assertNull($result['maintenance']);
    }

    /**
     * クライアントが古いバージョンの場合、最新のdeployを返す
     */
    public function test_check_version_returns_latest_deploy_when_client_is_outdated(): void
    {
        // Arrange
        $currentDeployId = 99;

        $latestDeploy = [
            'id' => 100,
            'deploy_key' => 202601020,
            'start_at' => '2026-01-15 10:00:00',
            'is_active' => true,
            'master_hash' => 'master_hash_002',
            'asset_hash' => 'asset_hash_002',
        ];

        $this->maintenanceRepository->shouldReceive('selectCurrent')
            ->once()
            ->andReturn(null);

        $this->deployRepository->shouldReceive('selectLatestDownloadable')
            ->once()
            ->andReturn($latestDeploy);

        // Act
        $result = $this->service->checkVersion($currentDeployId);

        // Assert
        $this->assertNotNull($result['deploy']);
        $this->assertEquals(100, $result['deploy']['id']);
        $this->assertEquals(202601020, $result['deploy']['deploy_key']);
        $this->assertNull($result['maintenance']);
    }

    /**
     * クライアントIDがnullの場合（初回アクセス）、最新のdeployを返す
     */
    public function test_check_version_returns_latest_deploy_when_client_id_is_null(): void
    {
        // Arrange
        $latestDeploy = [
            'id' => 100,
            'deploy_key' => 202601010,
            'start_at' => '2026-01-15 10:00:00',
            'is_active' => true,
        ];

        $this->maintenanceRepository->shouldReceive('selectCurrent')
            ->once()
            ->andReturn(null);

        $this->deployRepository->shouldReceive('selectLatestDownloadable')
            ->once()
            ->andReturn($latestDeploy);

        // Act
        $result = $this->service->checkVersion(null);

        // Assert
        $this->assertNotNull($result['deploy']);
        $this->assertEquals(100, $result['deploy']['id']);
    }

    /**
     * メンテナンス中の場合、メンテナンス情報を返す
     */
    public function test_check_version_returns_maintenance_info_when_active(): void
    {
        // Arrange
        $currentDeployId = 100;

        $latestDeploy = [
            'id' => 100,
            'deploy_key' => 202601010,
            'start_at' => '2026-01-15 10:00:00',
            'is_active' => true,
        ];

        $maintenance = [
            'id' => 1,
            'title' => 'Scheduled Maintenance',
            'message' => 'System will be under maintenance',
            'start_at' => '2026-01-15 12:00:00',
            'end_at' => '2026-01-15 14:00:00',
        ];

        $this->maintenanceRepository->shouldReceive('selectCurrent')
            ->once()
            ->andReturn($maintenance);

        $this->deployRepository->shouldReceive('selectLatestDownloadable')
            ->once()
            ->andReturn($latestDeploy);

        // Act
        $result = $this->service->checkVersion($currentDeployId);

        // Assert
        $this->assertNull($result['deploy']);
        $this->assertNotNull($result['maintenance']);
        $this->assertEquals('Scheduled Maintenance', $result['maintenance']['title']);
        $this->assertEquals('System will be under maintenance', $result['maintenance']['message']);
    }

    /**
     * 最新のdeployが存在しない場合、例外をスロー
     */
    public function test_check_version_throws_exception_when_no_deploy_exists(): void
    {
        // Arrange
        $this->maintenanceRepository->shouldReceive('selectCurrent')
            ->once()
            ->andReturn(null);

        $this->deployRepository->shouldReceive('selectLatestDownloadable')
            ->once()
            ->andReturn(null);

        // Assert & Act
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No downloadable deploy found');

        $this->service->checkVersion(100);
    }

    /**
     * 更新あり＋メンテナンス中の両方の情報を返す
     */
    public function test_check_version_returns_both_deploy_and_maintenance(): void
    {
        // Arrange
        $currentDeployId = 99;

        $latestDeploy = [
            'id' => 100,
            'deploy_key' => 202601020,
            'start_at' => '2026-01-15 10:00:00',
            'is_active' => true,
        ];

        $maintenance = [
            'id' => 1,
            'title' => 'Emergency Maintenance',
            'message' => 'Urgent system update',
            'start_at' => '2026-01-15 12:00:00',
            'end_at' => '2026-01-15 14:00:00',
        ];

        $this->maintenanceRepository->shouldReceive('selectCurrent')
            ->once()
            ->andReturn($maintenance);

        $this->deployRepository->shouldReceive('selectLatestDownloadable')
            ->once()
            ->andReturn($latestDeploy);

        // Act
        $result = $this->service->checkVersion($currentDeployId);

        // Assert
        $this->assertNotNull($result['deploy']);
        $this->assertEquals(100, $result['deploy']['id']);
        $this->assertNotNull($result['maintenance']);
        $this->assertEquals('Emergency Maintenance', $result['maintenance']['title']);
    }
}
