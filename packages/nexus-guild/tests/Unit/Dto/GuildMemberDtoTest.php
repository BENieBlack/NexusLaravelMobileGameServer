<?php

namespace NexusGuild\Tests\Unit\Dto;

use NexusGuild\Dto\GuildMemberDto;
use PHPUnit\Framework\TestCase;

/**
 * GuildMemberDtoのユニットテスト
 */
class GuildMemberDtoTest extends TestCase
{
    /**
     * @test
     * DTOを正常に作成できる
     */
    public function DTOを正常に作成できる(): void
    {
        // Act
        $dto = new GuildMemberDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            role: 'member',
            joinedAt: '2024-01-01 00:00:00',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Assert
        $this->assertSame(1, $dto->getId());
        $this->assertSame(100, $dto->getSysGuildId());
        $this->assertSame(200, $dto->getSysPlayerId());
        $this->assertSame('member', $dto->getRole());
        $this->assertSame('2024-01-01 00:00:00', $dto->getJoinedAt());
        $this->assertSame('2024-01-01 00:00:00', $dto->getCreatedAt());
        $this->assertSame('2024-01-01 12:00:00', $dto->getUpdatedAt());
    }

    /**
     * @test
     * toArray()で配列に変換できる
     */
    public function toArrayで配列に変換できる(): void
    {
        // Arrange
        $dto = new GuildMemberDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            role: 'member',
            joinedAt: '2024-01-01 00:00:00',
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
        $this->assertSame('member', $array['role']);
        $this->assertSame('2024-01-01 00:00:00', $array['joined_at']);
        $this->assertSame('2024-01-01 00:00:00', $array['created_at']);
        $this->assertSame('2024-01-01 12:00:00', $array['updated_at']);
    }

    /**
     * @test
     * memberロールで作成できる
     */
    public function memberロールで作成できる(): void
    {
        // Act
        $dto = new GuildMemberDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            role: 'member',
            joinedAt: '2024-01-01 00:00:00',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame('member', $dto->getRole());
    }

    /**
     * @test
     * leaderロールで作成できる
     */
    public function leaderロールで作成できる(): void
    {
        // Act
        $dto = new GuildMemberDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            role: 'leader',
            joinedAt: '2024-01-01 00:00:00',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame('leader', $dto->getRole());
    }

    /**
     * @test
     * officerロールで作成できる
     */
    public function officerロールで作成できる(): void
    {
        // Act
        $dto = new GuildMemberDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            role: 'officer',
            joinedAt: '2024-01-01 00:00:00',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame('officer', $dto->getRole());
    }

    /**
     * @test
     * 参加日時と作成日時が同じでも作成できる
     */
    public function 参加日時と作成日時が同じでも作成できる(): void
    {
        // Arrange
        $timestamp = '2024-01-01 00:00:00';

        // Act
        $dto = new GuildMemberDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            role: 'member',
            joinedAt: $timestamp,
            createdAt: $timestamp,
            updatedAt: $timestamp
        );

        // Assert
        $this->assertSame($timestamp, $dto->getJoinedAt());
        $this->assertSame($timestamp, $dto->getCreatedAt());
        $this->assertEquals($dto->getJoinedAt(), $dto->getCreatedAt());
    }

    /**
     * @test
     * 大きなIDでも作成できる
     */
    public function 大きなIDでも作成できる(): void
    {
        // Act
        $dto = new GuildMemberDto(
            id: 999999999,
            sysGuildId: 888888888,
            sysPlayerId: 777777777,
            role: 'member',
            joinedAt: '2024-01-01 00:00:00',
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
    public function toArrayのキーがスネークケースである(): void
    {
        // Arrange
        $dto = new GuildMemberDto(
            id: 1,
            sysGuildId: 100,
            sysPlayerId: 200,
            role: 'member',
            joinedAt: '2024-01-01 00:00:00',
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Act
        $array = $dto->toArray();

        // Assert
        $this->assertArrayHasKey('sys_guild_id', $array);
        $this->assertArrayHasKey('sys_player_id', $array);
        $this->assertArrayHasKey('joined_at', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
        $this->assertArrayNotHasKey('sysGuildId', $array);
        $this->assertArrayNotHasKey('sysPlayerId', $array);
    }
}
