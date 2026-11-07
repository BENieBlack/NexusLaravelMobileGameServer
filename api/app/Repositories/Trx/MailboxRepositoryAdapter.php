<?php

namespace App\Repositories\Trx;

use App\Adapters\Mailbox\MailboxAdapter;
use App\Domain\Mailbox\Constants\Category;
use App\Domain\Mailbox\Constants\Priority;
use App\Models\Trx\TrxMailbox;
use Illuminate\Support\Collection;
use NexusMailbox\DataTransferObjects\Mailbox;
use NexusMailbox\Repositories\MailboxRepositoryInterface;

/**
 * MailboxRepositoryAdapter
 *
 * nexus-mailboxパッケージのMailboxRepositoryInterfaceを実装し、
 * Application層のTrxMailboxRepositoryをラップする。
 *
 * Repositoryは常にModelを返し、DTOへの変換はこのアダプタが担う。
 * パッケージ側はApplication層のEloquent Modelに依存できないため、
 * 境界でDTOに詰め替える。
 */
class MailboxRepositoryAdapter implements MailboxRepositoryInterface
{
    public function __construct(
        private readonly TrxMailboxRepository $trxMailboxRepository,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @return Collection<int, Mailbox>
     */
    public function selectByPlayerId(
        int $sysPlayerId,
        ?\NexusMailbox\Constants\Category $category = null,
        ?\NexusMailbox\Constants\Priority $priority = null,
        bool $onlyUnread = false,
        bool $onlyLocked = false
    ): Collection {
        // パッケージ側のEnumをApplication層のEnumへ変換
        $categoryEnum = $category ? Category::fromString($category->value) : null;
        $priorityEnum = $priority ? Priority::fromString($priority->value) : null;

        $trxMailboxs = $this->trxMailboxRepository->selectByPlayerId(
            $sysPlayerId,
            $categoryEnum,
            $priorityEnum,
            $onlyUnread,
            $onlyLocked
        );

        return $trxMailboxs->map(fn (TrxMailbox $trxMailbox) => MailboxAdapter::toDto($trxMailbox));
    }

    /**
     * {@inheritDoc}
     */
    public function selectById(int $id): ?Mailbox
    {
        $trxMailbox = $this->trxMailboxRepository->selectById($id);

        return $trxMailbox ? MailboxAdapter::toDto($trxMailbox) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(Mailbox $mailbox): void
    {
        $trxMailbox = $this->trxMailboxRepository->selectById($mailbox->getId());

        if ($trxMailbox === null) {
            return;
        }

        $trxMailbox->setIsOpened($mailbox->isRead());
        $trxMailbox->setIsReceived($mailbox->isReceived());
        $trxMailbox->setIsProtected($mailbox->isLocked());

        $this->trxMailboxRepository->setModel($trxMailbox);
    }

    /**
     * {@inheritDoc}
     */
    public function markAsRead(Mailbox $mailbox): void
    {
        $trxMailbox = $this->trxMailboxRepository->selectById($mailbox->getId());

        if ($trxMailbox !== null) {
            $this->trxMailboxRepository->markAsOpened($trxMailbox);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function markDtoAsReceived(Mailbox $mailbox): void
    {
        $trxMailbox = $this->trxMailboxRepository->selectById($mailbox->getId());

        if ($trxMailbox !== null) {
            $this->trxMailboxRepository->markAsReceived($trxMailbox);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateLockStatus(Mailbox $mailbox, bool $isLocked): void
    {
        $trxMailbox = $this->trxMailboxRepository->selectById($mailbox->getId());

        if ($trxMailbox !== null) {
            $this->trxMailboxRepository->toggleProtection($trxMailbox, $isLocked);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, int>
     */
    public function countUnreadByCategory(int $sysPlayerId): array
    {
        return $this->trxMailboxRepository->countUnreadByCategory($sysPlayerId);
    }
}
