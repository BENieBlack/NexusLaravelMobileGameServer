<?php

namespace Tests\Unit\DTOs;

use NexusMaintenance\DTOs\MaintenanceDto;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\TestCase;

class MaintenanceDtoTest extends TestCase
{
    protected function tearDown(): void
    {
        ClockUtility::reset();
        parent::tearDown();
    }

    public function test_is_currently_under_maintenance_returns_false_when_not_maintenance_mode(): void
    {
        $sysMaintenance = new MaintenanceDto(
            isMaintenance: false,
            startAt: null,
            endAt: null,
        );

        $this->assertFalse($sysMaintenance->isCurrentlyUnderMaintenance());
    }

    public function test_is_currently_under_maintenance_returns_true_when_in_maintenance_mode_without_time_range(): void
    {
        $sysMaintenance = new MaintenanceDto(
            isMaintenance: true,
            startAt: null,
            endAt: null,
        );

        $this->assertTrue($sysMaintenance->isCurrentlyUnderMaintenance());
    }

    public function test_is_currently_under_maintenance_returns_false_before_start_time(): void
    {
        ClockUtility::setNow('2024-01-15 12:00:00');

        $sysMaintenance = new MaintenanceDto(
            isMaintenance: true,
            startAt: '2024-01-15 13:00:00', // 1時間後
            endAt: null,
        );

        $this->assertFalse($sysMaintenance->isCurrentlyUnderMaintenance());
    }

    public function test_is_currently_under_maintenance_returns_true_during_maintenance_period(): void
    {
        ClockUtility::setNow('2024-01-15 12:00:00');

        $sysMaintenance = new MaintenanceDto(
            isMaintenance: true,
            startAt: '2024-01-15 11:00:00', // 1時間前
            endAt: '2024-01-15 13:00:00',   // 1時間後
        );

        $this->assertTrue($sysMaintenance->isCurrentlyUnderMaintenance());
    }

    public function test_is_currently_under_maintenance_returns_false_after_end_time(): void
    {
        ClockUtility::setNow('2024-01-15 12:00:00');

        $sysMaintenance = new MaintenanceDto(
            isMaintenance: true,
            startAt: '2024-01-15 10:00:00', // 2時間前
            endAt: '2024-01-15 11:00:00',   // 1時間前
        );

        $this->assertFalse($sysMaintenance->isCurrentlyUnderMaintenance());
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $startAt = '2024-01-15 12:00:00';
        $endAt = '2024-01-15 13:00:00';
        $updatedAt = '2024-01-15 11:00:00';

        $sysMaintenance = new MaintenanceDto(
            isMaintenance: true,
            startAt: $startAt,
            endAt: $endAt,
            title: 'Maintenance Title',
            message: 'Maintenance message',
            updatedAt: $updatedAt,
        );

        $array = $sysMaintenance->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('is_maintenance', $array);
        $this->assertArrayHasKey('start_at', $array);
        $this->assertArrayHasKey('end_at', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('updated_at', $array);

        $this->assertTrue($array['is_maintenance']);
        $this->assertEquals($startAt, $array['start_at']);
        $this->assertEquals($endAt, $array['end_at']);
        $this->assertEquals('Maintenance Title', $array['title']);
        $this->assertEquals('Maintenance message', $array['message']);
        $this->assertEquals($updatedAt, $array['updated_at']);
    }

    public function test_from_array_creates_correct_instance(): void
    {
        $data = [
            'is_maintenance' => true,
            'start_at' => '2024-01-01 10:00:00',
            'end_at' => '2024-01-01 12:00:00',
            'title' => 'Test Maintenance',
            'message' => 'Test message',
            'updated_at' => '2024-01-01 09:00:00',
        ];

        $sysMaintenance = MaintenanceDto::fromArray($data);

        $this->assertTrue($sysMaintenance->getIsMaintenance());
        $this->assertEquals('2024-01-01 10:00:00', $sysMaintenance->getStartAt());
        $this->assertEquals('2024-01-01 12:00:00', $sysMaintenance->getEndAt());
        $this->assertEquals('Test Maintenance', $sysMaintenance->getTitle());
        $this->assertEquals('Test message', $sysMaintenance->getMessage());
        $this->assertEquals('2024-01-01 09:00:00', $sysMaintenance->getUpdatedAt());
    }

    public function test_to_json_returns_valid_json_string(): void
    {
        $sysMaintenance = new MaintenanceDto(
            isMaintenance: true,
            startAt: '2024-01-01 10:00:00',
            endAt: '2024-01-01 12:00:00',
            title: 'Maintenance',
            message: 'Scheduled maintenance',
        );

        $json = $sysMaintenance->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertTrue($decoded['is_maintenance']);
        $this->assertEquals('Maintenance', $decoded['title']);
    }
}
