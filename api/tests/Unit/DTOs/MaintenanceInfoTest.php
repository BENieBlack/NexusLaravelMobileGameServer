<?php

namespace Tests\Unit\DTOs;

use NexusMaintenance\DTOs\MaintenanceInfo;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class MaintenanceInfoTest extends TestCase
{
    public function test_is_currently_under_maintenance_returns_false_when_not_maintenance_mode(): void
    {
        $info = new MaintenanceInfo(
            isMaintenance: false,
            startAt: null,
            endAt: null,
        );

        $this->assertFalse($info->isCurrentlyUnderMaintenance());
    }

    public function test_is_currently_under_maintenance_returns_true_when_in_maintenance_mode_without_time_range(): void
    {
        $info = new MaintenanceInfo(
            isMaintenance: true,
            startAt: null,
            endAt: null,
        );

        $this->assertTrue($info->isCurrentlyUnderMaintenance());
    }

    public function test_is_currently_under_maintenance_returns_false_before_start_time(): void
    {
        $info = new MaintenanceInfo(
            isMaintenance: true,
            startAt: CarbonImmutable::now()->addHour(),
            endAt: null,
        );

        $this->assertFalse($info->isCurrentlyUnderMaintenance());
    }

    public function test_is_currently_under_maintenance_returns_true_during_maintenance_period(): void
    {
        $info = new MaintenanceInfo(
            isMaintenance: true,
            startAt: CarbonImmutable::now()->subHour(),
            endAt: CarbonImmutable::now()->addHour(),
        );

        $this->assertTrue($info->isCurrentlyUnderMaintenance());
    }

    public function test_is_currently_under_maintenance_returns_false_after_end_time(): void
    {
        $info = new MaintenanceInfo(
            isMaintenance: true,
            startAt: CarbonImmutable::now()->subHours(2),
            endAt: CarbonImmutable::now()->subHour(),
        );

        $this->assertFalse($info->isCurrentlyUnderMaintenance());
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $startAt = CarbonImmutable::now();
        $endAt = CarbonImmutable::now()->addHour();
        $updatedAt = CarbonImmutable::now();

        $info = new MaintenanceInfo(
            isMaintenance: true,
            startAt: $startAt,
            endAt: $endAt,
            title: 'Maintenance Title',
            message: 'Maintenance message',
            updatedAt: $updatedAt,
        );

        $array = $info->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('is_maintenance', $array);
        $this->assertArrayHasKey('start_at', $array);
        $this->assertArrayHasKey('end_at', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('updated_at', $array);
        
        $this->assertTrue($array['is_maintenance']);
        $this->assertEquals($startAt->toIso8601String(), $array['start_at']);
        $this->assertEquals($endAt->toIso8601String(), $array['end_at']);
        $this->assertEquals('Maintenance Title', $array['title']);
        $this->assertEquals('Maintenance message', $array['message']);
        $this->assertEquals($updatedAt->toIso8601String(), $array['updated_at']);
    }

    public function test_from_array_creates_correct_instance(): void
    {
        $data = [
            'is_maintenance' => true,
            'start_at' => '2024-01-01T10:00:00+00:00',
            'end_at' => '2024-01-01T12:00:00+00:00',
            'title' => 'Test Maintenance',
            'message' => 'Test message',
            'updated_at' => '2024-01-01T09:00:00+00:00',
        ];

        $info = MaintenanceInfo::fromArray($data);

        $this->assertTrue($info->isMaintenance);
        $this->assertInstanceOf(CarbonImmutable::class, $info->startAt);
        $this->assertInstanceOf(CarbonImmutable::class, $info->endAt);
        $this->assertEquals('Test Maintenance', $info->title);
        $this->assertEquals('Test message', $info->message);
        $this->assertInstanceOf(CarbonImmutable::class, $info->updatedAt);
    }

    public function test_to_json_returns_valid_json_string(): void
    {
        $info = new MaintenanceInfo(
            isMaintenance: true,
            startAt: CarbonImmutable::parse('2024-01-01T10:00:00+00:00'),
            endAt: CarbonImmutable::parse('2024-01-01T12:00:00+00:00'),
            title: 'Maintenance',
            message: 'Scheduled maintenance',
        );

        $json = $info->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertTrue($decoded['is_maintenance']);
        $this->assertEquals('Maintenance', $decoded['title']);
    }
}
