<?php

namespace NexusMailbox\Tests\Unit\Dto;

use NexusMailbox\Dto\MailboxDto;
use PHPUnit\Framework\TestCase;

/**
 * MailboxDtoのユニットテスト
 */
class MailboxDtoTest extends TestCase
{
    /**
     * @test
     * DTOを正常に作成できる
     */
    public function dt_oを正常に作成できる(): void
    {
        // Act
        $dto = new MailboxDto(
            id: 1,
            sysPlayerId: 100,
            mstMailboxId: 'welcome_mail_001',
            isRead: false,
            isReceived: false,
            isLocked: false,
            expiresAt: '2024-12-31 23:59:59',
            createdAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame(1, $dto->getId());
        $this->assertSame(100, $dto->getSysPlayerId());
        $this->assertSame('welcome_mail_001', $dto->getMstMailboxId());
        $this->assertFalse($dto->isRead());
        $this->assertFalse($dto->isReceived());
        $this->assertFalse($dto->isLocked());
        $this->assertSame('2024-12-31 23:59:59', $dto->getExpiresAt());
        $this->assertSame('2024-01-01 00:00:00', $dto->getCreatedAt());
    }

    /**
     * @test
     * 既読フラグを設定できる
     */
    public function 既読フラグを設定できる(): void
    {
        // Arrange
        $dto = new MailboxDto(
            id: 1,
            sysPlayerId: 100,
            mstMailboxId: 'mail_001',
            isRead: false,
            isReceived: false,
            isLocked: false,
            expiresAt: null,
            createdAt: '2024-01-01 00:00:00'
        );

        // Act
        $dto->setIsRead(true);

        // Assert
        $this->assertTrue($dto->isRead());
    }

    /**
     * @test
     * 受取済みフラグを設定できる
     */
    public function 受取済みフラグを設定できる(): void
    {
        // Arrange
        $dto = new MailboxDto(
            id: 1,
            sysPlayerId: 100,
            mstMailboxId: 'mail_001',
            isRead: false,
            isReceived: false,
            isLocked: false,
            expiresAt: null,
            createdAt: '2024-01-01 00:00:00'
        );

        // Act
        $dto->setIsReceived(true);

        // Assert
        $this->assertTrue($dto->isReceived());
    }

    /**
     * @test
     * ロックフラグを設定できる
     */
    public function ロックフラグを設定できる(): void
    {
        // Arrange
        $dto = new MailboxDto(
            id: 1,
            sysPlayerId: 100,
            mstMailboxId: 'mail_001',
            isRead: false,
            isReceived: false,
            isLocked: false,
            expiresAt: null,
            createdAt: '2024-01-01 00:00:00'
        );

        // Act
        $dto->setIsLocked(true);

        // Assert
        $this->assertTrue($dto->isLocked());
    }

    /**
     * @test
     * 有効期限がnullでも作成できる
     */
    public function 有効期限がnullでも作成できる(): void
    {
        // Act
        $dto = new MailboxDto(
            id: 1,
            sysPlayerId: 100,
            mstMailboxId: 'permanent_mail',
            isRead: false,
            isReceived: false,
            isLocked: false,
            expiresAt: null,
            createdAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertNull($dto->getExpiresAt());
    }

    /**
     * @test
     * 全フラグがtrueで作成できる
     */
    public function 全フラグがtrueで作成できる(): void
    {
        // Act
        $dto = new MailboxDto(
            id: 1,
            sysPlayerId: 100,
            mstMailboxId: 'mail_001',
            isRead: true,
            isReceived: true,
            isLocked: true,
            expiresAt: '2024-12-31 23:59:59',
            createdAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertTrue($dto->isRead());
        $this->assertTrue($dto->isReceived());
        $this->assertTrue($dto->isLocked());
    }

    /**
     * @test
     * フラグを複数回切り替えできる
     */
    public function フラグを複数回切り替えできる(): void
    {
        // Arrange
        $dto = new MailboxDto(
            id: 1,
            sysPlayerId: 100,
            mstMailboxId: 'mail_001',
            isRead: false,
            isReceived: false,
            isLocked: false,
            expiresAt: null,
            createdAt: '2024-01-01 00:00:00'
        );

        // Act & Assert
        $this->assertFalse($dto->isRead());

        $dto->setIsRead(true);
        $this->assertTrue($dto->isRead());

        $dto->setIsRead(false);
        $this->assertFalse($dto->isRead());

        $dto->setIsRead(true);
        $this->assertTrue($dto->isRead());
    }

    /**
     * @test
     * マスターメールボックスIDが長い文字列でも保持できる
     */
    public function マスターメールボックス_i_dが長い文字列でも保持できる(): void
    {
        // Arrange
        $longId = 'event_special_2024_new_year_celebration_bonus_reward_mail';

        // Act
        $dto = new MailboxDto(
            id: 1,
            sysPlayerId: 100,
            mstMailboxId: $longId,
            isRead: false,
            isReceived: false,
            isLocked: false,
            expiresAt: null,
            createdAt: '2024-01-01 00:00:00'
        );

        // Assert
        $this->assertSame($longId, $dto->getMstMailboxId());
    }
}
