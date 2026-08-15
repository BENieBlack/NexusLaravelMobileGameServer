<?php

namespace App\Http\Responses\Guild;

use App\Http\Responses\_BaseResponse;
use NexusGuild\DataTransferObjects\Guild;

/**
 * GuildDetailResponse
 *
 * ギルド詳細取得APIのレスポンス
 */
class GuildDetailResponse extends _BaseResponse
{
    public function __construct(
        public readonly int $guildId,
        public readonly string $name,
        public readonly string $description,
        public readonly int $level,
        public readonly int $exp,
        public readonly int $maxMembers,
        public readonly int $currentMembers,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /**
     * Guildからレスポンスを生成
     */
    public static function fromDto(Guild $guild): self
    {
        return new self(
            guildId: $guild->getId(),
            name: $guild->getName(),
            description: $guild->getDescription(),
            level: $guild->getLevel(),
            exp: $guild->getExp(),
            maxMembers: $guild->getMaxMembers(),
            currentMembers: $guild->getCurrentMembers(),
            createdAt: $guild->getCreatedAt(),
            updatedAt: $guild->getUpdatedAt(),
        );
    }

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'guild_id' => $this->guildId,
            'name' => $this->name,
            'description' => $this->description,
            'level' => $this->level,
            'exp' => $this->exp,
            'max_members' => $this->maxMembers,
            'current_members' => $this->currentMembers,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
