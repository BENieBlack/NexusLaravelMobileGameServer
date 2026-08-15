<?php

namespace App\Http\Responses\Guild;

use App\Http\Responses\_BaseResponse;
use NexusGuild\DataTransferObjects\Guild;

/**
 * GuildListResponse
 *
 * ギルド一覧取得APIのレスポンス
 */
class GuildListResponse extends _BaseResponse
{
    /**
     * @param  array<array<string, mixed>>  $guilds
     */
    public function __construct(
        public readonly array $guilds,
    ) {}

    /**
     * Guild配列からレスポンスを生成
     *
     * @param  array<Guild>  $guildDtos
     */
    public static function fromDtoArray(array $guildDtos): self
    {
        $guilds = [];
        foreach ($guildDtos as $dto) {
            $guilds[] = [
                'sys_guild_id' => $dto->getId(),
                'name' => $dto->getName(),
                'description' => $dto->getDescription(),
                'level' => $dto->getLevel(),
                'exp' => $dto->getExp(),
                'max_members' => $dto->getMaxMembers(),
                'current_members' => $dto->getCurrentMembers(),
                'created_at' => $dto->getCreatedAt(),
            ];
        }

        return new self($guilds);
    }

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'guilds' => $this->guilds,
        ];
    }
}
