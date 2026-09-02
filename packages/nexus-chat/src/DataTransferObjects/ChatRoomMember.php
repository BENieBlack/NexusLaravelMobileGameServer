<?php

namespace NexusChat\DataTransferObjects;

use NexusChat\Constants\ChatRoomRole;

/**
 * ChatRoomMember
 *
 * グループチャットのメンバーデータ転送オブジェクト
 * FRIEND / GUILD チャットでは使用しない
 */
readonly class ChatRoomMember
{
    public function __construct(
        public int $id,
        public int $chatRoomId,
        public int $playerId,
        public string $playerName,
        public ChatRoomRole $role,
        public string $joinedAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getChatRoomId(): int
    {
        return $this->chatRoomId;
    }

    public function getPlayerId(): int
    {
        return $this->playerId;
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function getRole(): ChatRoomRole
    {
        return $this->role;
    }

    public function canInvite(): bool
    {
        return $this->role->canInvite();
    }

    public function canKick(): bool
    {
        return $this->role->canKick();
    }

    public function getJoinedAt(): string
    {
        return $this->joinedAt;
    }
}
