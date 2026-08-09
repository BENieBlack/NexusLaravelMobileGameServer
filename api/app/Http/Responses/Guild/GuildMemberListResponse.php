<?php

namespace App\Http\Responses\Guild;

use App\Http\Responses\_BaseResponse;
use NexusGuild\Dto\GuildMemberDto;

/**
 * GuildMemberListResponse
 *
 * ギルドメンバー一覧取得APIのレスポンス
 */
class GuildMemberListResponse extends _BaseResponse
{
    /**
     * @param  array<array<string, mixed>>  $members
     */
    public function __construct(
        public readonly array $members,
    ) {}

    /**
     * GuildMemberDto配列からレスポンスを生成
     *
     * @param  array<GuildMemberDto>  $memberDtos
     */
    public static function fromDtoArray(array $memberDtos): self
    {
        $members = [];
        foreach ($memberDtos as $dto) {
            $members[] = [
                'member_id' => $dto->getId(),
                'guild_id' => $dto->getSysGuildId(),
                'player_id' => $dto->getSysPlayerId(),
                'role' => $dto->getRole(),
                'joined_at' => $dto->getJoinedAt(),
            ];
        }

        return new self($members);
    }

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'members' => $this->members,
        ];
    }
}
