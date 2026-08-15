<?php

namespace NexusFriend\Tests\Unit\Dto;

use NexusFriend\Dto\FriendDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * FriendDtoのユニットテスト
 */
class FriendDtoTest extends TestCase
{
    /**
     * DTOを正常に作成できる
     */
    #[Test]
    public function dt_oを正常に作成できる(): void
    {
        // Act
        $dto = new FriendDto(
            playerId: 12345,
            myId: 'friend_abc123',
            name: 'TestPlayer',
            level: 50
        );

        // Assert
        $this->assertSame(12345, $dto->getPlayerId());
        $this->assertSame('friend_abc123', $dto->getMyId());
        $this->assertSame('TestPlayer', $dto->getName());
        $this->assertSame(50, $dto->getLevel());
    }

    /**
     * 公開プロパティから直接アクセスできる
     */
    #[Test]
    public function 公開プロパティから直接アクセスできる(): void
    {
        // Act
        $dto = new FriendDto(
            playerId: 12345,
            myId: 'friend_abc123',
            name: 'TestPlayer',
            level: 50
        );

        // Assert
        $this->assertSame(12345, $dto->playerId);
        $this->assertSame('friend_abc123', $dto->myId);
        $this->assertSame('TestPlayer', $dto->name);
        $this->assertSame(50, $dto->level);
    }

    /**
     * レベル1で作成できる
     */
    #[Test]
    public function レベル1で作成できる(): void
    {
        // Act
        $dto = new FriendDto(
            playerId: 100,
            myId: 'new_player',
            name: 'Beginner',
            level: 1
        );

        // Assert
        $this->assertSame(1, $dto->getLevel());
    }

    /**
     * 高レベルプレイヤーで作成できる
     */
    #[Test]
    public function 高レベルプレイヤーで作成できる(): void
    {
        // Act
        $dto = new FriendDto(
            playerId: 999,
            myId: 'veteran_player',
            name: 'MaxLevel',
            level: 9999
        );

        // Assert
        $this->assertSame(9999, $dto->getLevel());
    }

    /**
     * 長い名前を保持できる
     */
    #[Test]
    public function 長い名前を保持できる(): void
    {
        // Arrange
        $longName = 'VeryLongPlayerNameWith50Characters12345678901234';

        // Act
        $dto = new FriendDto(
            playerId: 100,
            myId: 'player_123',
            name: $longName,
            level: 10
        );

        // Assert
        $this->assertSame($longName, $dto->getName());
        $this->assertGreaterThan(40, strlen($dto->getName()));
    }

    /**
     * 特殊文字を含む名前を保持できる
     */
    #[Test]
    public function 特殊文字を含む名前を保持できる(): void
    {
        // Arrange
        $specialName = 'プレイヤー★2024';

        // Act
        $dto = new FriendDto(
            playerId: 100,
            myId: 'player_jp',
            name: $specialName,
            level: 25
        );

        // Assert
        $this->assertSame($specialName, $dto->getName());
    }

    /**
     * マイIDが長い文字列でも保持できる
     */
    #[Test]
    public function マイ_i_dが長い文字列でも保持できる(): void
    {
        // Arrange
        $longMyId = 'very_long_my_id_string_with_uuid_format_12345678901234567890';

        // Act
        $dto = new FriendDto(
            playerId: 100,
            myId: $longMyId,
            name: 'Player',
            level: 1
        );

        // Assert
        $this->assertSame($longMyId, $dto->getMyId());
    }

    /**
     * readonlyクラスのため変更不可
     */
    #[Test]
    public function readonlyクラスのため変更不可(): void
    {
        // Arrange
        $dto = new FriendDto(
            playerId: 100,
            myId: 'player_123',
            name: 'Original',
            level: 10
        );

        // Assert - readonly property cannot be modified
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        // Act - attempt to modify readonly property
        $dto->name = 'Modified';
    }
}
