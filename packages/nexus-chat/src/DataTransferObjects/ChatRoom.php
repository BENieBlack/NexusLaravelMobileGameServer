<?php

namespace NexusChat\DataTransferObjects;

use NexusChat\Constants\ChatRoomType;

/**
 * ChatRoom
 *
 * チャットルームデータ転送オブジェクト
 *
 * フレンドDM:     room_key = "{小さいID}_{大きいID}"
 * ギルドチャット: room_key = "{guild_id}"
 * グループ:       room_key = "{chat_room_id}"（IDをそのまま使用）
 */
readonly class ChatRoom
{
    public function __construct(
        public int $id,
        public ChatRoomType $type,
        public string $roomKey,
        public string $name,
        public ?int $guildId,
        public int $memberCount,
        public string $createdAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): ChatRoomType
    {
        return $this->type;
    }

    /**
     * Reverbブロードキャストチャンネル名を返す
     *
     * フレンドDM:  private-chat.friend.{room_key}  ← 2人しか購読できない private channel
     * ギルド:      presence-chat.guild.{room_key}   ← オンライン人数が見える presence channel
     * グループ:    private-chat.group.{room_key}    ← 招待メンバーのみ購読できる private channel
     */
    public function getChannelName(): string
    {
        return match ($this->type) {
            ChatRoomType::FRIEND => "private-chat.friend.{$this->roomKey}",
            ChatRoomType::GUILD  => "presence-chat.guild.{$this->roomKey}",
            ChatRoomType::GROUP  => "private-chat.group.{$this->roomKey}",
        };
    }

    public function getRoomKey(): string
    {
        return $this->roomKey;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getGuildId(): ?int
    {
        return $this->guildId;
    }

    public function getMemberCount(): int
    {
        return $this->memberCount;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
