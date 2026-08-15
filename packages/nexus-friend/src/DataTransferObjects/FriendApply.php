<?php

namespace NexusFriend\DataTransferObjects;


/**
 * FriendApply
 *
 * フレンド申請データ転送オブジェクト
 */
readonly class FriendApply
{
    public function __construct(
        public int $id,
        public int $senderPlayerId,
        public int $receiverPlayerId,
        public string $status,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}

    /**
     * IDを取得
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * 送信者プレイヤーIDを取得
     */
    public function getSenderPlayerId(): int
    {
        return $this->senderPlayerId;
    }

    /**
     * 受信者プレイヤーIDを取得
     */
    public function getReceiverPlayerId(): int
    {
        return $this->receiverPlayerId;
    }

    /**
     * ステータスを取得
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * 作成日時を取得
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /**
     * 更新日時を取得
     */
    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }
}
