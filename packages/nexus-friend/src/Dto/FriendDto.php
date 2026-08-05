<?php

namespace NexusFriend\Dto;

/**
 * FriendDto
 * 
 * フレンド情報データ転送オブジェクト
 */
readonly class FriendDto
{
    public function __construct(
        public int $playerId,
        public string $myId,
        public string $name,
        public int $level,
    ) {
    }

    /**
     * プレイヤーIDを取得
     *
     * @return int
     */
    public function getPlayerId(): int
    {
        return $this->playerId;
    }

    /**
     * マイIDを取得
     *
     * @return string
     */
    public function getMyId(): string
    {
        return $this->myId;
    }

    /**
     * 名前を取得
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * レベルを取得
     *
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }
}
