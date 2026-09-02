<?php

namespace NexusNotification\DataTransferObjects;

use NexusNotification\Constants\NotificationType;

/**
 * Notification
 *
 * ゲーム内通知データ転送オブジェクト
 */
readonly class Notification
{
    public function __construct(
        public int $id,
        public int $playerId,
        public NotificationType $type,
        public string $title,
        public string $body,
        public array $payload,
        public bool $isRead,
        public ?string $readAt,
        public string $createdAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getPlayerId(): int
    {
        return $this->playerId;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * 追加データ（ミッションID、フレンドID等）
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function getReadAt(): ?string
    {
        return $this->readAt;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
