<?php

namespace NexusMailbox\Tests\Unit\Dto;

use NexusMailbox\Dto\MailboxDto;
use PHPUnit\Framework\TestCase;

class MailboxDtoTest extends TestCase
{
    public function test_constructor_sets_properties_correctly(): void
    {
        $dto = new MailboxDto(
            id: 1,
            sysPlayerId: 100,
            mstMailboxId: 'mail_001',
            isRead: false,
            isReceived: false,
            isLocked: false,
            expiresAt: '2026-12-31 23:59:59',
            createdAt: '2026-01-01 00:00:00'
        );

        $this->assertSame(1, $dto->getId());
        $this->assertSame(100, $dto->getSysPlayerId());
        $this->assertSame('mail_001', $dto->getMstMailboxId());
        $this->assertFalse($dto->isRead());
        $this->assertFalse($dto->isReceived());
        $this->assertFalse($dto->isLocked());
        $this->assertSame('2026-12-31 23:59:59', $dto->getExpiresAt());
        $this->assertSame('2026-01-01 00:00:00', $dto->getCreatedAt());
    }

    public function test_set_is_read_updates_read_status(): void
    {
        $dto = new MailboxDto(
            id: 1,
            sysPlayerId: 100,
            mstMailboxId: 'mail_001',
            isRead: false,
            isReceived: false,
            isLocked: false,
            expiresAt: null,
            createdAt: '2026-01-01 00:00:00'
        );

        $this->assertFalse($dto->isRead());
        
        $dto->setIsRead(true);
        
        $this->assertTrue($dto->isRead());
    }

    public function test_set_is_received_updates_received_status(): void
    {
        $dto = new MailboxDto(
            id: 1,
            sysPlayerId: 100,
            mstMailboxId: 'mail_001',
            isRead: false,
            isReceived: false,
            isLocked: false,
            expiresAt: null,
            createdAt: '2026-01-01 00:00:00'
        );

        $this->assertFalse($dto->isReceived());
        
        $dto->setIsReceived(true);
        
        $this->assertTrue($dto->isReceived());
    }

    public function test_set_is_locked_updates_locked_status(): void
    {
        $dto = new MailboxDto(
            id: 1,
            sysPlayerId: 100,
            mstMailboxId: 'mail_001',
            isRead: false,
            isReceived: false,
            isLocked: false,
            expiresAt: null,
            createdAt: '2026-01-01 00:00:00'
        );

        $this->assertFalse($dto->isLocked());
        
        $dto->setIsLocked(true);
        
        $this->assertTrue($dto->isLocked());
    }

    public function test_expires_at_can_be_null(): void
    {
        $dto = new MailboxDto(
            id: 1,
            sysPlayerId: 100,
            mstMailboxId: 'mail_001',
            isRead: false,
            isReceived: false,
            isLocked: false,
            expiresAt: null,
            createdAt: '2026-01-01 00:00:00'
        );

        $this->assertNull($dto->getExpiresAt());
    }
}
