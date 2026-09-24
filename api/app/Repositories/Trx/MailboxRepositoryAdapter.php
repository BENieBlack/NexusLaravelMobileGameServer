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

        $models = $this->trxMailboxRepository->selectByPlayerId(
            $sysPlayerId,
            $categoryEnum,
            $priorityEnum,
            $onlyUnread,
            $onlyLocked
        );

        return $models->map(fn (TrxMailbox $model) => MailboxAdapter::toDto($model));
    }

    /**
     * {@inheritDoc}
     */
    public function selectById(int $id): ?Mailbox
    {
        $model = $this->trxMailboxRepository->selectById($id);

        return $model ? MailboxAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(Mailbox $mailbox): void
    {
        $model = $this->trxMailboxRepository->selectById($mailbox->getId());

        if ($model === null) {
            return;
        }

        $model->setIsOpened($mailbox->isRead());
        $model->setIsReceived($mailbox->isReceived());
        $model->setIsProtected($mailbox->isLocked());

        $this->trxMailboxRepository->setModel($model);
    }

    /**
     * {@inheritDoc}
     */
    public function markAsRead(Mailbox $mailbox): void
    {
        $model = $this->trxMailboxRepository->selectById($mailbox->getId());

        if ($model !== null) {
            $this->trxMailboxRepository->markAsOpened($model);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function markDtoAsReceived(Mailbox $mailbox): void
    {
        $model = $this->trxMailboxRepository->selectById($mailbox->getId());

        if ($model !== null) {
            $this->trxMailboxRepository->markAsReceived($model);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateLockStatus(Mailbox $mailbox, bool $isLocked): void
    {
        $model = $this->trxMailboxRepository->selectById($mailbox->getId());

        if ($model !== null) {
            $this->trxMailboxRepository->toggleProtection($model, $isLocked);
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
