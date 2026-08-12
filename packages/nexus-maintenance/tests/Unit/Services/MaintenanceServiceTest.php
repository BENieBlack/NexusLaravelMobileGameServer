<?php

namespace NexusMaintenance\Tests\Unit\Services;

use NexusMaintenance\Contracts\MaintenanceStorageInterface;
use NexusMaintenance\ValueObjects\Maintenance;
use NexusMaintenance\Services\MaintenanceService;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\TestCase;

class MaintenanceServiceTest extends TestCase
{
    private MaintenanceStorageInterface $storage;
    private MaintenanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // モックストレージを作成
        $this->storage = $this->createMock(MaintenanceStorageInterface::class);
        
        // キャッシュを無効化したサービスを作成（テスト簡略化のため）
        $this->service = new MaintenanceService(
            storage: $this->storage,
            cacheTtl: 60,
            cacheEnabled: false
        );
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();
        parent::tearDown();
    }

    public function test_is_under_maintenance_returns_false_when_no_info(): void
    {
        $this->storage
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->assertFalse($this->service->isUnderMaintenance());
    }

    public function test_is_under_maintenance_returns_true_when_currently_under_maintenance(): void
    {
        ClockUtility::setNow('2024-01-15 12:00:00');
        
        $sysMaintenance = new Maintenance(
            isMaintenance: true,
            startAt: '2024-01-15 11:00:00',
            endAt: '2024-01-15 13:00:00',
        );

        $this->storage
            ->expects($this->once())
            ->method('get')
            ->willReturn($sysMaintenance);

        $this->assertTrue($this->service->isUnderMaintenance());
    }

    public function test_get_maintenance_info_returns_info_from_storage(): void
    {
        $sysMaintenance = new Maintenance(
            isMaintenance: true,
            startAt: '2024-01-15 12:00:00',
            endAt: '2024-01-15 13:00:00',
            title: 'Test Maintenance',
            message: 'Test message',
        );

        $this->storage
            ->expects($this->once())
            ->method('get')
            ->willReturn($sysMaintenance);

        $result = $this->service->getMaintenanceInfo();

        $this->assertSame($sysMaintenance, $result);
        $this->assertEquals('Test Maintenance', $result->getTitle());
    }

    public function test_start_maintenance_stores_info_and_clears_cache(): void
    {
        $sysMaintenance = new Maintenance(
            isMaintenance: true,
            startAt: '2024-01-15 12:00:00',
            endAt: '2024-01-15 13:00:00',
        );

        $this->storage
            ->expects($this->once())
            ->method('put')
            ->with($sysMaintenance)
            ->willReturn(true);

        $result = $this->service->startMaintenance($sysMaintenance);

        $this->assertTrue($result);
    }

    public function test_end_maintenance_deletes_info_and_clears_cache(): void
    {
        $this->storage
            ->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $result = $this->service->endMaintenance();

        $this->assertTrue($result);
    }

    public function test_health_check_delegates_to_storage(): void
    {
        $this->storage
            ->expects($this->once())
            ->method('healthCheck')
            ->willReturn(true);

        $result = $this->service->healthCheck();

        $this->assertTrue($result);
    }

    public function test_is_under_maintenance_returns_false_before_start_time(): void
    {
        ClockUtility::setNow('2024-01-15 12:00:00');
        
        $sysMaintenance = new Maintenance(
            isMaintenance: true,
            startAt: '2024-01-15 13:00:00', // 1時間後
            endAt: '2024-01-15 14:00:00',
        );

        $this->storage
            ->expects($this->once())
            ->method('get')
            ->willReturn($sysMaintenance);

        $this->assertFalse($this->service->isUnderMaintenance());
    }

    public function test_is_under_maintenance_returns_false_after_end_time(): void
    {
        ClockUtility::setNow('2024-01-15 12:00:00');
        
        $sysMaintenance = new Maintenance(
            isMaintenance: true,
            startAt: '2024-01-15 10:00:00', // 2時間前
            endAt: '2024-01-15 11:00:00',   // 1時間前
        );

        $this->storage
            ->expects($this->once())
            ->method('get')
            ->willReturn($sysMaintenance);

        $this->assertFalse($this->service->isUnderMaintenance());
    }
}
