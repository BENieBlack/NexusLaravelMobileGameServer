<?php

namespace NexusFriend\Tests\Unit\DataTransferObjects;

use Carbon\CarbonImmutable;
use NexusFriend\DataTransferObjects\FriendApply;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * FriendApplyのユニットテスト
 */
class FriendApplyDtoTest extends TestCase
{
    /**
     * DTOを正常に作成できる
     */
    #[Test]
    public function dt_oを正常に作成できる(): void
    {
        // Arrange
        $createdAt = new CarbonImmutable('2024-01-01 00:00:00');
        $updatedAt = new CarbonImmutable('2024-01-01 12:00:00');

        // Act
        $dto = new FriendApply(
            id: 1,
            senderPlayerId: 100,
            receiverPlayerId: 200,
            status: 'pending',
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        // Assert
        $this->assertSame(1, $dto->getId());
        $this->assertSame(100, $dto->getSenderPlayerId());
        $this->assertSame(200, $dto->getReceiverPlayerId());
        $this->assertSame('pending', $dto->getStatus());
        $this->assertSame($createdAt, $dto->getCreatedAt());
        $this->assertSame($updatedAt, $dto->getUpdatedAt());
    }

    /**
     * 公開プロパティから直接アクセスできる
     */
    #[Test]
    public function 公開プロパティから直接アクセスできる(): void
    {
        // Arrange
        $createdAt = new CarbonImmutable('2024-01-01 00:00:00');
        $updatedAt = new CarbonImmutable('2024-01-01 12:00:00');

        // Act
        $dto = new FriendApply(
            id: 1,
            senderPlayerId: 100,
            receiverPlayerId: 200,
            status: 'pending',
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        // Assert
        $this->assertSame(1, $dto->id);
        $this->assertSame(100, $dto->senderPlayerId);
        $this->assertSame(200, $dto->receiverPlayerId);
        $this->assertSame('pending', $dto->status);
        $this->assertSame($createdAt, $dto->createdAt);
        $this->assertSame($updatedAt, $dto->updatedAt);
    }

    /**
     * pendingステータスで作成できる
     */
    #[Test]
    public function pendingステータスで作成できる(): void
    {
        // Act
        $dto = new FriendApply(
            id: 1,
            senderPlayerId: 100,
            receiverPlayerId: 200,
            status: 'pending',
            createdAt: new CarbonImmutable,
            updatedAt: new CarbonImmutable
        );

        // Assert
        $this->assertSame('pending', $dto->getStatus());
    }

    /**
     * acceptedステータスで作成できる
     */
    #[Test]
    public function acceptedステータスで作成できる(): void
    {
        // Act
        $dto = new FriendApply(
            id: 1,
            senderPlayerId: 100,
            receiverPlayerId: 200,
            status: 'accepted',
            createdAt: new CarbonImmutable,
            updatedAt: new CarbonImmutable
        );

        // Assert
        $this->assertSame('accepted', $dto->getStatus());
    }

    /**
     * rejectedステータスで作成できる
     */
    #[Test]
    public function rejectedステータスで作成できる(): void
    {
        // Act
        $dto = new FriendApply(
            id: 1,
            senderPlayerId: 100,
            receiverPlayerId: 200,
            status: 'rejected',
            createdAt: new CarbonImmutable,
            updatedAt: new CarbonImmutable
        );

        // Assert
        $this->assertSame('rejected', $dto->getStatus());
    }

    /**
     * 作成日時と更新日時が同じでも作成できる
     */
    #[Test]
    public function 作成日時と更新日時が同じでも作成できる(): void
    {
        // Arrange
        $timestamp = new CarbonImmutable('2024-01-01 00:00:00');

        // Act
        $dto = new FriendApply(
            id: 1,
            senderPlayerId: 100,
            receiverPlayerId: 200,
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
     * 更新日時が作成日時より後でも作成できる
     */
    #[Test]
    public function 更新日時が作成日時より後でも作成できる(): void
    {
        // Arrange
        $createdAt = new CarbonImmutable('2024-01-01 00:00:00');
        $updatedAt = new CarbonImmutable('2024-01-15 00:00:00');

        // Act
        $dto = new FriendApply(
            id: 1,
            senderPlayerId: 100,
            receiverPlayerId: 200,
            status: 'accepted',
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        // Assert
        $this->assertLessThan($dto->getUpdatedAt(), $dto->getCreatedAt());
    }

    /**
     * 同じプレイヤーID間の申請も作成できる
     */
    #[Test]
    public function 同じプレイヤー_i_d間の申請も作成できる(): void
    {
        // Act
        $dto = new FriendApply(
            id: 1,
            senderPlayerId: 100,
            receiverPlayerId: 100,
            status: 'pending',
            createdAt: new CarbonImmutable,
            updatedAt: new CarbonImmutable
        );

        // Assert
        $this->assertSame(100, $dto->getSenderPlayerId());
        $this->assertSame(100, $dto->getReceiverPlayerId());
    }

    /**
     * 大きなIDでも作成できる
     */
    #[Test]
    public function 大きな_i_dでも作成できる(): void
    {
        // Act
        $dto = new FriendApply(
            id: 999999999,
            senderPlayerId: 888888888,
            receiverPlayerId: 777777777,
            status: 'pending',
            createdAt: new CarbonImmutable,
            updatedAt: new CarbonImmutable
        );

        // Assert
        $this->assertSame(999999999, $dto->getId());
        $this->assertSame(888888888, $dto->getSenderPlayerId());
        $this->assertSame(777777777, $dto->getReceiverPlayerId());
    }

    /**
     * readonlyクラスのため変更不可
     */
    #[Test]
    public function readonlyクラスのため変更不可(): void
    {
        // Arrange
        $dto = new FriendApply(
            id: 1,
            senderPlayerId: 100,
            receiverPlayerId: 200,
            status: 'pending',
            createdAt: new CarbonImmutable,
            updatedAt: new CarbonImmutable
        );

        // Assert - readonly property cannot be modified
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        // Act - attempt to modify readonly property
        $dto->status = 'accepted';
    }
}
