<?php

namespace NexusGuild\Dto;

/**
 * GuildMemberDto
 * 
 * ギルドメンバー情報のデータ転送オブジェクト
 */
class GuildMemberDto
{
    public function __construct(
        private readonly int $id,
        private readonly int $sysGuildId,
        private readonly int $sysPlayerId,
        private readonly string $role,
        private readonly string $joinedAt,
        private readonly string $createdAt,
        private readonly string $updatedAt,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSysGuildId(): int
    {
        return $this->sysGuildId;
    }

    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getJoinedAt(): string
    {
        return $this->joinedAt;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    /**
     * 配列に変換
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sys_guild_id' => $this->sysGuildId,
            'sys_player_id' => $this->sysPlayerId,
            'role' => $this->role,
            'joined_at' => $this->joinedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
