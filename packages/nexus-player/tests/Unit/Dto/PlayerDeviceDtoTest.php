<?php

namespace NexusPlayer\Tests\Unit\Dto;

use NexusPlayer\Dto\PlayerDeviceDto;
use PHPUnit\Framework\TestCase;

/**
 * PlayerDeviceDtoのユニットテスト
 */
class PlayerDeviceDtoTest extends TestCase
{
    /**
     * @test
     * DTOを正常に作成できる
     */
    public function DTOを正常に作成できる(): void
    {
        // Act
        $dto = new PlayerDeviceDto(
            id: 1,
            sysPlayerId: 100,
            uuid: 'test-uuid-12345',
            deviceInfo: ['os' => 'iOS', 'version' => '16.0'],
            lastLoginAt: '2024-01-01 12:00:00',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Assert
        $this->assertSame(1, $dto->getId());
        $this->assertSame(100, $dto->getSysPlayerId());
        $this->assertSame('test-uuid-12345', $dto->getUuid());
        $this->assertSame(['os' => 'iOS', 'version' => '16.0'], $dto->getDeviceInfo());
        $this->assertSame('2024-01-01 12:00:00', $dto->getLastLoginAt());
        $this->assertSame('2024-01-01 00:00:00', $dto->getCreatedAt());
        $this->assertSame('2024-01-01 12:00:00', $dto->getUpdatedAt());
    }

    /**
     * @test
     * デバイス情報がnullでも作成できる
     */
    public function デバイス情報がnullでも作成できる(): void
    {
        // Act
        $dto = new PlayerDeviceDto(
            id: 1,
            sysPlayerId: 100,
            uuid: 'test-uuid',
            deviceInfo: null,
            lastLoginAt: '2024-01-01 12:00:00',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Assert
        $this->assertNull($dto->getDeviceInfo());
    }

    /**
     * @test
     * デバイス情報に複雑なデータを持てる
     */
    public function デバイス情報に複雑なデータを持てる(): void
    {
        // Arrange
        $deviceInfo = [
            'os' => 'Android',
            'version' => '13',
            'model' => 'Pixel 7',
            'screen' => [
                'width' => 1080,
                'height' => 2400,
            ],
            'language' => 'ja',
        ];

        // Act
        $dto = new PlayerDeviceDto(
            id: 1,
            sysPlayerId: 100,
            uuid: 'android-uuid',
            deviceInfo: $deviceInfo,
            lastLoginAt: '2024-01-01 12:00:00',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Assert
        $this->assertSame($deviceInfo, $dto->getDeviceInfo());
        $this->assertSame('Android', $dto->getDeviceInfo()['os']);
        $this->assertSame(1080, $dto->getDeviceInfo()['screen']['width']);
    }

    /**
     * @test
     * UUIDが長い文字列でも保持できる
     */
    public function UUIDが長い文字列でも保持できる(): void
    {
        // Arrange
        $longUuid = str_repeat('a', 255);

        // Act
        $dto = new PlayerDeviceDto(
            id: 1,
            sysPlayerId: 100,
            uuid: $longUuid,
            deviceInfo: null,
            lastLoginAt: '2024-01-01 12:00:00',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Assert
        $this->assertSame($longUuid, $dto->getUuid());
        $this->assertSame(255, strlen($dto->getUuid()));
    }

    /**
     * @test
     * 異なるタイムスタンプで作成できる
     */
    public function 異なるタイムスタンプで作成できる(): void
    {
        // Act
        $dto = new PlayerDeviceDto(
            id: 1,
            sysPlayerId: 100,
            uuid: 'test-uuid',
            deviceInfo: null,
            lastLoginAt: '2024-12-31 23:59:59',
            createdAt: '2024-01-01 00:00:01',
            updatedAt: '2024-06-15 12:30:45'
        );

        // Assert
        $this->assertSame('2024-12-31 23:59:59', $dto->getLastLoginAt());
        $this->assertSame('2024-01-01 00:00:01', $dto->getCreatedAt());
        $this->assertSame('2024-06-15 12:30:45', $dto->getUpdatedAt());
    }
}
