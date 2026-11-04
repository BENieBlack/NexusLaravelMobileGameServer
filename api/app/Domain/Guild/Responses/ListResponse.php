<?php

namespace App\Domain\Guild\Responses;

use App\Domain\_BaseResponse;
use NexusGuild\DataTransferObjects\Guild;

/**
 * ListResponse
 *
 * ギルド一覧取得APIのレスポンス
 */
class ListResponse extends _BaseResponse
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
    public static function from(array $guildDtos): self
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
            'guild_list' => $this->guilds,
        ];
    }
}
