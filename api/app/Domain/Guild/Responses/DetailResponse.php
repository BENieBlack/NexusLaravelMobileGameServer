<?php

namespace App\Domain\Guild\Responses;

use App\Domain\_BaseResponse;
use NexusGuild\DataTransferObjects\Guild;

/**
 * DetailResponse
 *
 * ギルド詳細取得APIのレスポンス
 */
class DetailResponse extends _BaseResponse
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
    public static function from(Guild $guild): self
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
            'sys_guild_id' => $this->guildId,
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
