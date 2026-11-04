<?php

namespace App\Http\Responses\Guild;

use App\Http\Responses\_BaseResponse;
use NexusGuild\DataTransferObjects\GuildApply;

/**
 * ApplyListResponse
 *
 * ギルド加入申請一覧取得APIのレスポンス
 */
class ApplyListResponse extends _BaseResponse
{
    /**
     * @param  array<array<string, mixed>>  $applies
     */
    public function __construct(
        public readonly array $applies,
    ) {}

    /**
     * GuildApply配列からレスポンスを生成
     *
     * @param  array<GuildApply>  $guildApplies
     */
    public static function fromResult(array $guildApplies): self
    {
        $applies = [];
        foreach ($guildApplies as $dto) {
            $applies[] = [
                'sys_guild_apply_id' => $dto->getId(),
                'sys_guild_id' => $dto->getSysGuildId(),
                'sys_player_id' => $dto->getSysPlayerId(),
                'status' => $dto->getStatus(),
                'created_at' => $dto->getCreatedAt(),
            ];
        }

        return new self($applies);
    }

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'guild_apply_list' => $this->applies,
        ];
    }
}
