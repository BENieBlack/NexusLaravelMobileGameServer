<?php

namespace NexusGuild\DataTransferObjects;

/**
 * GuildApply
 *
 * ギルド加入申請のデータ転送オブジェクト
 */
class GuildApply
{
    public function __construct(
        private readonly int $id,
        private readonly int $sysGuildId,
        private readonly int $sysPlayerId,
        private readonly string $status,
        private readonly string $createdAt,
        private readonly string $updatedAt,
    ) {}

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

    public function getStatus(): string
    {
        return $this->status;
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
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
