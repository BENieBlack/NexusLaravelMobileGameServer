<?php

namespace NexusGuild\Tests\Unit\Dto;

use NexusGuild\Dto\GuildDto;
use PHPUnit\Framework\TestCase;

/**
 * GuildDtoのユニットテスト
 */
class GuildDtoTest extends TestCase
{
    /**
     * @test
     * DTOを正常に作成できる
     */
    public function DTOを正常に作成できる(): void
    {
        // Act
        $dto = new GuildDto(
            id: 1,
            name: 'Test Guild',
            description: 'A test guild for unit testing',
            level: 5,
            exp: 1000,
            maxMembers: 50,
            currentMembers: 10,
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Assert
        $this->assertSame(1, $dto->getId());
        $this->assertSame('Test Guild', $dto->getName());
        $this->assertSame('A test guild for unit testing', $dto->getDescription());
        $this->assertSame(5, $dto->getLevel());
        $this->assertSame(1000, $dto->getExp());
        $this->assertSame(50, $dto->getMaxMembers());
        $this->assertSame(10, $dto->getCurrentMembers());
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
        $dto = new GuildDto(
            id: 1,
            name: 'Test Guild',
            description: 'Description',
            level: 5,
            exp: 1000,
            maxMembers: 50,
            currentMembers: 10,
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Act
        $array = $dto->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertSame(1, $array['id']);
        $this->assertSame('Test Guild', $array['name']);
        $this->assertSame('Description', $array['description']);
        $this->assertSame(5, $array['level']);
        $this->assertSame(1000, $array['exp']);
        $this->assertSame(50, $array['max_members']);
        $this->assertSame(10, $array['current_members']);
        $this->assertSame('2024-01-01 00:00:00', $array['created_at']);
        $this->assertSame('2024-01-01 12:00:00', $array['updated_at']);
    }

    /**
     * @test
     * レベル1・経験値0で作成できる
     */
    public function レベル1経験値0で作成できる(): void
    {
        // Act
        $dto = new GuildDto(
            id: 1,
            name: 'New Guild',
            description: 'Brand new guild',
            level: 1,
            exp: 0,
            maxMembers: 30,
            currentMembers: 1,
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame(1, $dto->getLevel());
        $this->assertSame(0, $dto->getExp());
        $this->assertSame(1, $dto->getCurrentMembers());
    }

    /**
     * @test
     * 満員のギルドを作成できる
     */
    public function 満員のギルドを作成できる(): void
    {
        // Act
        $dto = new GuildDto(
            id: 1,
            name: 'Full Guild',
            description: 'Full capacity',
            level: 10,
            exp: 5000,
            maxMembers: 50,
            currentMembers: 50,
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Assert
        $this->assertSame(50, $dto->getMaxMembers());
        $this->assertSame(50, $dto->getCurrentMembers());
        $this->assertSame($dto->getMaxMembers(), $dto->getCurrentMembers());
    }

    /**
     * @test
     * 空のギルドを作成できる
     */
    public function 空のギルドを作成できる(): void
    {
        // Act
        $dto = new GuildDto(
            id: 1,
            name: 'Empty Guild',
            description: 'No members',
            level: 1,
            exp: 0,
            maxMembers: 50,
            currentMembers: 0,
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame(0, $dto->getCurrentMembers());
    }

    /**
     * @test
     * 長い名前と説明を保持できる
     */
    public function 長い名前と説明を保持できる(): void
    {
        // Arrange
        $longName = 'VeryLongGuildNameThatExceedsNormalLengthLimits123456';
        $longDescription = 'This is a very long description that contains multiple sentences. ' .
            'It describes the guild in great detail. ' .
            'It has many characters to test the DTO behavior with long text.';

        // Act
        $dto = new GuildDto(
            id: 1,
            name: $longName,
            description: $longDescription,
            level: 1,
            exp: 0,
            maxMembers: 50,
            currentMembers: 1,
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame($longName, $dto->getName());
        $this->assertSame($longDescription, $dto->getDescription());
    }

    /**
     * @test
     * 高レベル・高経験値のギルドを作成できる
     */
    public function 高レベル高経験値のギルドを作成できる(): void
    {
        // Act
        $dto = new GuildDto(
            id: 999,
            name: 'Max Level Guild',
            description: 'Top tier guild',
            level: 100,
            exp: 9999999,
            maxMembers: 100,
            currentMembers: 95,
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-12-31 23:59:59'
        );

        // Assert
        $this->assertSame(100, $dto->getLevel());
        $this->assertSame(9999999, $dto->getExp());
        $this->assertSame(100, $dto->getMaxMembers());
    }

    /**
     * @test
     * 特殊文字を含む名前を保持できる
     */
    public function 特殊文字を含む名前を保持できる(): void
    {
        // Arrange
        $specialName = 'ギルド★2024';
        $specialDescription = '最強のギルド！みんな集まれ♪';

        // Act
        $dto = new GuildDto(
            id: 1,
            name: $specialName,
            description: $specialDescription,
            level: 1,
            exp: 0,
            maxMembers: 50,
            currentMembers: 1,
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame($specialName, $dto->getName());
        $this->assertSame($specialDescription, $dto->getDescription());
    }

    /**
     * @test
     * toArray()のキーがスネークケースである
     */
    public function toArrayのキーがスネークケースである(): void
    {
        // Arrange
        $dto = new GuildDto(
            id: 1,
            name: 'Test Guild',
            description: 'Description',
            level: 1,
            exp: 0,
            maxMembers: 50,
            currentMembers: 10,
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 12:00:00'
        );

        // Act
        $array = $dto->toArray();

        // Assert
        $this->assertArrayHasKey('max_members', $array);
        $this->assertArrayHasKey('current_members', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
        $this->assertArrayNotHasKey('maxMembers', $array);
        $this->assertArrayNotHasKey('currentMembers', $array);
    }
}
