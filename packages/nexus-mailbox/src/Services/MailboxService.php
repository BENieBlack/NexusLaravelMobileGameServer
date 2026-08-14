<?php

namespace NexusMailbox\Services;

use Illuminate\Support\Collection;
use NexusMailbox\Constants\Category;
use NexusMailbox\Constants\Priority;
use NexusMailbox\Dto\MailboxDto;
use NexusMailbox\Repositories\MailboxRepositoryInterface;

/**
 * MailboxService
 *
 * メールボックスのビジネスロジックを担当するサービス
 */
class MailboxService
{
    public function __construct(
        private readonly MailboxRepositoryInterface $mailboxRepository,
    ) {}

    /**
     * メールボックス一覧を取得
     *
     * @return Collection<MailboxDto>
     */
    public function getMailboxList(
        int $sysPlayerId,
        ?Category $category = null,
        ?Priority $priority = null,
        bool $onlyUnread = false,
        bool $onlyLocked = false
    ): Collection {
        return $this->mailboxRepository->selectByPlayerId(
            $sysPlayerId,
            $category,
            $priority,
            $onlyUnread,
            $onlyLocked
        );
    }

    /**
     * カテゴリ別未読数を取得
     *
     * @return array<string, int>
     */
    public function countUnread(int $sysPlayerId): array
    {
        return $this->mailboxRepository->countUnreadByCategory($sysPlayerId);
    }

    /**
     * メールを既読にする
     */
    public function markAsRead(int $mailboxId, int $sysPlayerId): bool
    {
        $mailbox = $this->mailboxRepository->selectById($mailboxId);

        if ($mailbox === null || $mailbox->getSysPlayerId() !== $sysPlayerId) {
            return false;
        }

        if ($mailbox->isRead()) {
            return true; // 既に既読
        }

        $mailbox->setIsRead(true);
        $this->mailboxRepository->markAsRead($mailbox);

        return true;
    }

    /**
     * メールのロック状態を変更
     */
    public function updateLockStatus(int $mailboxId, int $sysPlayerId, bool $isLocked): bool
    {
        $mailbox = $this->mailboxRepository->selectById($mailboxId);

        if ($mailbox === null || $mailbox->getSysPlayerId() !== $sysPlayerId) {
            return false;
        }

        $mailbox->setIsLocked($isLocked);
        $this->mailboxRepository->updateLockStatus($mailbox, $isLocked);

        return true;
    }

    /**
     * メールを受取済みにする
     */
    public function markAsReceived(int $mailboxId, int $sysPlayerId): ?MailboxDto
    {
        $mailbox = $this->mailboxRepository->selectById($mailboxId);

        if ($mailbox === null || $mailbox->getSysPlayerId() !== $sysPlayerId) {
            return null;
        }

        if ($mailbox->isReceived()) {
            return null; // 既に受取済み
        }

        $mailbox->setIsReceived(true);
        $this->mailboxRepository->markDtoAsReceived($mailbox);

        return $mailbox;
    }
}
