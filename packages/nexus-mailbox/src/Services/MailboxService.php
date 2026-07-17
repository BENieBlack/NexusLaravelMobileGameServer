<?php

namespace NexusMailbox\Services;

use NexusMailbox\Dto\MailboxDto;
use NexusMailbox\Repositories\MailboxRepositoryInterface;
use NexusMailbox\Constants\Category;
use NexusMailbox\Constants\Priority;

/**
 * MailboxService
 * 
 * メールボックスのビジネスロジックを担当するサービス
 */
class MailboxService
{
    public function __construct(
        private readonly MailboxRepositoryInterface $mailboxRepository,
    ) {
    }

    /**
     * メールボックス一覧を取得
     *
     * @param int $sysPlayerId
     * @param Category|null $category
     * @param Priority|null $priority
     * @param bool $onlyUnread
     * @param bool $onlyLocked
     * @return \Illuminate\Support\Collection<MailboxDto>
     */
    public function getMailboxList(
        int $sysPlayerId,
        ?Category $category = null,
        ?Priority $priority = null,
        bool $onlyUnread = false,
        bool $onlyLocked = false
    ): \Illuminate\Support\Collection {
        return $this->mailboxRepository->findByPlayerId(
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
     * @param int $sysPlayerId
     * @return array<string, int>
     */
    public function getUnreadCounts(int $sysPlayerId): array
    {
        return $this->mailboxRepository->countUnreadByCategory($sysPlayerId);
    }

    /**
     * メールを既読にする
     *
     * @param int $mailboxId
     * @param int $sysPlayerId
     * @return bool
     */
    public function markAsRead(int $mailboxId, int $sysPlayerId): bool
    {
        $mailbox = $this->mailboxRepository->findById($mailboxId);

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
     *
     * @param int $mailboxId
     * @param int $sysPlayerId
     * @param bool $isLocked
     * @return bool
     */
    public function updateLockStatus(int $mailboxId, int $sysPlayerId, bool $isLocked): bool
    {
        $mailbox = $this->mailboxRepository->findById($mailboxId);

        if ($mailbox === null || $mailbox->getSysPlayerId() !== $sysPlayerId) {
            return false;
        }

        $mailbox->setIsLocked($isLocked);
        $this->mailboxRepository->updateLockStatus($mailbox, $isLocked);

        return true;
    }

    /**
     * メールを受取済みにする
     *
     * @param int $mailboxId
     * @param int $sysPlayerId
     * @return MailboxDto|null
     */
    public function markAsReceived(int $mailboxId, int $sysPlayerId): ?MailboxDto
    {
        $mailbox = $this->mailboxRepository->findById($mailboxId);

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
