<?php

namespace App\Repositories\Trx;

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

        return $models->map(fn (TrxMailbox $model) => $this->convertToDto($model));
    }

    /**
     * {@inheritDoc}
     */
    public function selectById(int $id): ?Mailbox
    {
        $model = $this->trxMailboxRepository->selectById($id);

        return $model ? $this->convertToDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(Mailbox $mailboxDto): void
    {
        $model = $this->trxMailboxRepository->selectById($mailboxDto->getId());

        if ($model === null) {
            return;
        }

        $model->setIsOpened($mailboxDto->isRead());
        $model->setIsReceived($mailboxDto->isReceived());
        $model->setIsProtected($mailboxDto->isLocked());

        $this->trxMailboxRepository->setModel($model);
    }

    /**
     * {@inheritDoc}
     */
    public function markAsRead(Mailbox $mailboxDto): void
    {
        $model = $this->trxMailboxRepository->selectById($mailboxDto->getId());

        if ($model !== null) {
            $this->trxMailboxRepository->markAsOpened($model);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function markDtoAsReceived(Mailbox $mailboxDto): void
    {
        $model = $this->trxMailboxRepository->selectById($mailboxDto->getId());

        if ($model !== null) {
            $this->trxMailboxRepository->markAsReceived($model);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateLockStatus(Mailbox $mailboxDto, bool $isLocked): void
    {
        $model = $this->trxMailboxRepository->selectById($mailboxDto->getId());

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

    /**
     * Eloquent ModelをDTOに変換
     */
    private function convertToDto(TrxMailbox $model): Mailbox
    {
        return new Mailbox(
            id: $model->getId(),
            sysPlayerId: $model->getSysPlayerId(),
            mstMailboxId: $model->getMstMailboxId(),
            isRead: $model->getIsOpened(),
            isReceived: $model->getIsReceived(),
            isLocked: $model->getIsProtected(),
            expiresAt: $model->getExpiresAt(),
            createdAt: (string) $model->created_at,
        );
    }
}
