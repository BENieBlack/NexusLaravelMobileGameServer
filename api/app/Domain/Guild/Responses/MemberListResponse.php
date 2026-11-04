<?php

namespace App\Domain\Guild\Responses;

use App\Domain\_BaseResponse;
use NexusGuild\DataTransferObjects\GuildMember;

/**
 * MemberListResponse
 *
 * ギルドメンバー一覧取得APIのレスポンス
 */
class MemberListResponse extends _BaseResponse
{
    /**
     * @param  array<array<string, mixed>>  $members
     */
    public function __construct(
        public readonly array $members,
    ) {}

    /**
     * GuildMember配列からレスポンスを生成
     *
     * @param  array<GuildMember>  $guildMembers
     */
    public static function fromResult(array $guildMembers): self
    {
        $members = [];
        foreach ($guildMembers as $dto) {
            $members[] = [
                'sys_guild_member_id' => $dto->getId(),
                'sys_guild_id' => $dto->getSysGuildId(),
                'sys_player_id' => $dto->getSysPlayerId(),
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
            'guild_member_list' => $this->members,
        ];
    }
}
