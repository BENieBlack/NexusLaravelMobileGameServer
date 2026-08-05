<?php

namespace NexusFriend\Dto;

use DateTime;

/**
 * FriendApplyDto
 * 
 * フレンド申請データ転送オブジェクト
 */
readonly class FriendApplyDto
{
    public function __construct(
        public int $id,
        public int $senderPlayerId,
        public int $receiverPlayerId,
        public string $status,
        public DateTime $createdAt,
        public DateTime $updatedAt,
    ) {
    }

    /**
     * IDを取得
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * 送信者プレイヤーIDを取得
     *
     * @return int
     */
    public function getSenderPlayerId(): int
    {
        return $this->senderPlayerId;
    }

    /**
     * 受信者プレイヤーIDを取得
     *
     * @return int
     */
    public function getReceiverPlayerId(): int
    {
        return $this->receiverPlayerId;
    }

    /**
     * ステータスを取得
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * 作成日時を取得
     *
     * @return DateTime
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * 更新日時を取得
     *
     * @return DateTime
     */
    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }
}
