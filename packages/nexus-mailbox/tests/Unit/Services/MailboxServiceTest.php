<?php

namespace NexusMailbox\Tests\Unit\Services;

use Illuminate\Support\Collection;
use NexusMailbox\Constants\Category;
use NexusMailbox\Dto\MailboxDto;
use NexusMailbox\Repositories\MailboxRepositoryInterface;
use NexusMailbox\Services\MailboxService;
use PHPUnit\Framework\TestCase;

class MailboxServiceTest extends TestCase
{
    private MailboxRepositoryInterface $mockRepository;

    private MailboxService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = $this->createMock(MailboxRepositoryInterface::class);
        $this->service = new MailboxService($this->mockRepository);
    }

    public function test_get_mailbox_list_calls_repository(): void
    {
        $sysPlayerId = 100;
        $expectedCollection = new Collection([
            new MailboxDto(1, $sysPlayerId, 'mail_001', false, false, false, null, '2026-01-01 00:00:00'),
        ]);

        $this->mockRepository
            ->expects($this->once())
            ->method('findByPlayerId')
            ->with($sysPlayerId, null, null, false, false)
            ->willReturn($expectedCollection);

        $result = $this->service->getMailboxList($sysPlayerId);

        $this->assertSame($expectedCollection, $result);
    }

    public function test_get_mailbox_list_with_category_filter(): void
    {
        $sysPlayerId = 100;
        $category = Category::SYSTEM;
        $expectedCollection = new Collection;

        $this->mockRepository
            ->expects($this->once())
            ->method('findByPlayerId')
            ->with($sysPlayerId, $category, null, false, false)
            ->willReturn($expectedCollection);

        $result = $this->service->getMailboxList($sysPlayerId, $category);

        $this->assertSame($expectedCollection, $result);
    }

    public function test_get_unread_counts_calls_repository(): void
    {
        $sysPlayerId = 100;
        $expectedCounts = ['system' => 5, 'operation' => 3];

        $this->mockRepository
            ->expects($this->once())
            ->method('countUnreadByCategory')
            ->with($sysPlayerId)
            ->willReturn($expectedCounts);

        $result = $this->service->countUnread($sysPlayerId);

        $this->assertSame($expectedCounts, $result);
    }

    public function test_mark_as_read_returns_false_when_mailbox_not_found(): void
    {
        $mailboxId = 1;
        $sysPlayerId = 100;

        $this->mockRepository
            ->expects($this->once())
            ->method('findById')
            ->with($mailboxId)
            ->willReturn(null);

        $result = $this->service->markAsRead($mailboxId, $sysPlayerId);

        $this->assertFalse($result);
    }

    public function test_mark_as_read_returns_false_when_player_mismatch(): void
    {
        $mailboxId = 1;
        $sysPlayerId = 100;
        $differentPlayerId = 999;

        $mailbox = new MailboxDto(
            $mailboxId,
            $differentPlayerId,
            'mail_001',
            false,
            false,
            false,
            null,
            '2026-01-01 00:00:00'
        );

        $this->mockRepository
            ->expects($this->once())
            ->method('findById')
            ->with($mailboxId)
            ->willReturn($mailbox);

        $result = $this->service->markAsRead($mailboxId, $sysPlayerId);

        $this->assertFalse($result);
    }

    public function test_mark_as_read_returns_true_when_already_read(): void
    {
        $mailboxId = 1;
        $sysPlayerId = 100;

        $mailbox = new MailboxDto(
            $mailboxId,
            $sysPlayerId,
            'mail_001',
            true, // already read
            false,
            false,
            null,
            '2026-01-01 00:00:00'
        );

        $this->mockRepository
            ->expects($this->once())
            ->method('findById')
            ->with($mailboxId)
            ->willReturn($mailbox);

        $result = $this->service->markAsRead($mailboxId, $sysPlayerId);

        $this->assertTrue($result);
    }

    public function test_mark_as_read_marks_unread_mailbox_as_read(): void
    {
        $mailboxId = 1;
        $sysPlayerId = 100;

        $mailbox = new MailboxDto(
            $mailboxId,
            $sysPlayerId,
            'mail_001',
            false,
            false,
            false,
            null,
            '2026-01-01 00:00:00'
        );

        $this->mockRepository
            ->expects($this->once())
            ->method('findById')
            ->with($mailboxId)
            ->willReturn($mailbox);

        $this->mockRepository
            ->expects($this->once())
            ->method('markAsRead')
            ->with($this->callback(function ($dto) {
                return $dto->isRead() === true;
            }));

        $result = $this->service->markAsRead($mailboxId, $sysPlayerId);

        $this->assertTrue($result);
    }

    public function test_update_lock_status_returns_false_when_mailbox_not_found(): void
    {
        $mailboxId = 1;
        $sysPlayerId = 100;

        $this->mockRepository
            ->expects($this->once())
            ->method('findById')
            ->with($mailboxId)
            ->willReturn(null);

        $result = $this->service->updateLockStatus($mailboxId, $sysPlayerId, true);

        $this->assertFalse($result);
    }

    public function test_update_lock_status_updates_lock_status(): void
    {
        $mailboxId = 1;
        $sysPlayerId = 100;

        $mailbox = new MailboxDto(
            $mailboxId,
            $sysPlayerId,
            'mail_001',
            false,
            false,
            false,
            null,
            '2026-01-01 00:00:00'
        );

        $this->mockRepository
            ->expects($this->once())
            ->method('findById')
            ->with($mailboxId)
            ->willReturn($mailbox);

        $this->mockRepository
            ->expects($this->once())
            ->method('updateLockStatus')
            ->with(
                $this->callback(function ($dto) {
                    return $dto->isLocked() === true;
                }),
                true
            );

        $result = $this->service->updateLockStatus($mailboxId, $sysPlayerId, true);

        $this->assertTrue($result);
    }

    public function test_mark_as_received_returns_null_when_mailbox_not_found(): void
    {
        $mailboxId = 1;
        $sysPlayerId = 100;

        $this->mockRepository
            ->expects($this->once())
            ->method('findById')
            ->with($mailboxId)
            ->willReturn(null);

        $result = $this->service->markAsReceived($mailboxId, $sysPlayerId);

        $this->assertNull($result);
    }

    public function test_mark_as_received_returns_null_when_already_received(): void
    {
        $mailboxId = 1;
        $sysPlayerId = 100;

        $mailbox = new MailboxDto(
            $mailboxId,
            $sysPlayerId,
            'mail_001',
            false,
            true, // already received
            false,
            null,
            '2026-01-01 00:00:00'
        );

        $this->mockRepository
            ->expects($this->once())
            ->method('findById')
            ->with($mailboxId)
            ->willReturn($mailbox);

        $result = $this->service->markAsReceived($mailboxId, $sysPlayerId);

        $this->assertNull($result);
    }

    public function test_mark_as_received_marks_mailbox_as_received(): void
    {
        $mailboxId = 1;
        $sysPlayerId = 100;

        $mailbox = new MailboxDto(
            $mailboxId,
            $sysPlayerId,
            'mail_001',
            false,
            false,
            false,
            null,
            '2026-01-01 00:00:00'
        );

        $this->mockRepository
            ->expects($this->once())
            ->method('findById')
            ->with($mailboxId)
            ->willReturn($mailbox);

        $this->mockRepository
            ->expects($this->once())
            ->method('markDtoAsReceived')
            ->with($this->callback(function ($dto) {
                return $dto->isReceived() === true;
            }));

        $result = $this->service->markAsReceived($mailboxId, $sysPlayerId);

        $this->assertInstanceOf(MailboxDto::class, $result);
        $this->assertTrue($result->isReceived());
    }
}
