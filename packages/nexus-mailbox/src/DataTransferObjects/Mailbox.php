<?php

namespace NexusMailbox\DataTransferObjects;

/**
 * Mailbox
 *
 * メールボックスアイテムを表すDTO
 */
class Mailbox
{
    public function __construct(
        private readonly int $id,
        private readonly int $sysPlayerId,
        private readonly string $mstMailboxId,
        private bool $isRead,
        private bool $isReceived,
        private bool $isLocked,
        private readonly ?string $expiresAt,
        private readonly string $createdAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    public function getMstMailboxId(): string
    {
        return $this->mstMailboxId;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): void
    {
        $this->isRead = $isRead;
    }

    public function isReceived(): bool
    {
        return $this->isReceived;
    }

    public function setIsReceived(bool $isReceived): void
    {
        $this->isReceived = $isReceived;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function setIsLocked(bool $isLocked): void
    {
        $this->isLocked = $isLocked;
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
