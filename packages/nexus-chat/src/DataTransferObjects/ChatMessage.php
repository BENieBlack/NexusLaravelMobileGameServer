<?php

namespace NexusChat\DataTransferObjects;

/**
 * ChatMessage
 *
 * チャットメッセージデータ転送オブジェクト
 */
readonly class ChatMessage
{
    public function __construct(
        public int $id,
        public int $chatRoomId,
        public int $senderPlayerId,
        public string $senderName,
        public string $body,
        public bool $isDeleted,
        public string $createdAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getChatRoomId(): int
    {
        return $this->chatRoomId;
    }

    public function getSenderPlayerId(): int
    {
        return $this->senderPlayerId;
    }

    public function getSenderName(): string
    {
        return $this->senderName;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
