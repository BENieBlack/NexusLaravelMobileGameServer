<?php

namespace App\Http\Responses\Guild;

use App\Http\Responses\_BaseResponse;
use NexusGuild\Dto\GuildDto;

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
    ) {
    }

    /**
     * GuildDtoからレスポンスを生成
     *
     * @param GuildDto $guildDto
     * @return self
     */
    public static function fromDto(GuildDto $guildDto): self
    {
        return new self(
            guildId: $guildDto->getId(),
            name: $guildDto->getName(),
            description: $guildDto->getDescription(),
            level: $guildDto->getLevel(),
            exp: $guildDto->getExp(),
            maxMembers: $guildDto->getMaxMembers(),
            currentMembers: $guildDto->getCurrentMembers(),
            createdAt: $guildDto->getCreatedAt(),
            updatedAt: $guildDto->getUpdatedAt(),
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
