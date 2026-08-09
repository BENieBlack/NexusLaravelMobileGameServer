<?php

namespace NexusGuild\Tests\Unit\Dto;

use NexusGuild\Dto\GuildApplyDto;
use PHPUnit\Framework\TestCase;

/**
 * GuildApplyDtoのユニットテスト
 */
class GuildApplyDtoTest extends TestCase
{
    /**
     * @test
     * DTOを正常に作成できる
     */
    public function dt_oを正常に作成できる(): void
    {
        // Act
        $dto = new GuildApplyDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            status: 'pending',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Assert
        $this->assertSame(1, $dto->getId());
        $this->assertSame(100, $dto->getSysGuildId());
        $this->assertSame(200, $dto->getSysPlayerId());
        $this->assertSame('pending', $dto->getStatus());
        $this->assertSame('2024-01-01 00:00:00', $dto->getCreatedAt());
        $this->assertSame('2024-01-01 12:00:00', $dto->getUpdatedAt());
    }

    /**
     * @test
     * toArray()で配列に変換できる
     */
    public function to_arrayで配列に変換できる(): void
    {
        // Arrange
        $dto = new GuildApplyDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            status: 'pending',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Act
        $array = $dto->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertSame(1, $array['id']);
        $this->assertSame(100, $array['sys_guild_id']);
        $this->assertSame(200, $array['sys_player_id']);
        $this->assertSame('pending', $array['status']);
        $this->assertSame('2024-01-01 00:00:00', $array['created_at']);
        $this->assertSame('2024-01-01 12:00:00', $array['updated_at']);
    }

    /**
     * @test
     * pendingステータスで作成できる
     */
    public function pendingステータスで作成できる(): void
    {
        // Act
        $dto = new GuildApplyDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            status: 'pending',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame('pending', $dto->getStatus());
    }

    /**
     * @test
     * approvedステータスで作成できる
     */
    public function approvedステータスで作成できる(): void
    {
        // Act
        $dto = new GuildApplyDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            status: 'approved',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Assert
        $this->assertSame('approved', $dto->getStatus());
    }

    /**
     * @test
     * rejectedステータスで作成できる
     */
    public function rejectedステータスで作成できる(): void
    {
        // Act
        $dto = new GuildApplyDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            status: 'rejected',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Assert
        $this->assertSame('rejected', $dto->getStatus());
    }

    /**
     * @test
     * cancelledステータスで作成できる
     */
    public function cancelledステータスで作成できる(): void
    {
        // Act
        $dto = new GuildApplyDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            status: 'cancelled',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Assert
        $this->assertSame('cancelled', $dto->getStatus());
    }

    /**
     * @test
     * 作成日時と更新日時が同じでも作成できる
     */
    public function 作成日時と更新日時が同じでも作成できる(): void
    {
        // Arrange
        $timestamp = '2024-01-01 00:00:00';

        // Act
        $dto = new GuildApplyDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            status: 'pending',
            createdAt: $timestamp,
            updatedAt: $timestamp
        );

        // Assert
        $this->assertSame($timestamp, $dto->getCreatedAt());
        $this->assertSame($timestamp, $dto->getUpdatedAt());
        $this->assertEquals($dto->getCreatedAt(), $dto->getUpdatedAt());
    }

    /**
     * @test
     * 大きなIDでも作成できる
     */
    public function 大きな_i_dでも作成できる(): void
    {
        // Act
        $dto = new GuildApplyDto(
            id: 999999999,
            sysGuildId: 888888888,
            sysPlayerId: 777777777,
            status: 'pending',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame(999999999, $dto->getId());
        $this->assertSame(888888888, $dto->getSysGuildId());
        $this->assertSame(777777777, $dto->getSysPlayerId());
    }

    /**
     * @test
     * toArray()のキーがスネークケースである
     */
    public function to_arrayのキーがスネークケースである(): void
    {
        // Arrange
        $dto = new GuildApplyDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            status: 'pending',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Act
        $array = $dto->toArray();

        // Assert
        $this->assertArrayHasKey('sys_guild_id', $array);
        $this->assertArrayHasKey('sys_player_id', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
        $this->assertArrayNotHasKey('sysGuildId', $array);
        $this->assertArrayNotHasKey('sysPlayerId', $array);
    }
}
