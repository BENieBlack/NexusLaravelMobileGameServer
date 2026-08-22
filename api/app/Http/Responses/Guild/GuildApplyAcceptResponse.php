<?php

namespace App\Http\Responses\Guild;

use App\Http\Responses\_BaseResponse;
use NexusGuild\DataTransferObjects\GuildApply;

/**
 * GuildApplyAcceptResponse
 *
 * ギルド加入申請承認APIのレスポンス
 */
class GuildApplyAcceptResponse extends _BaseResponse
{
    public function __construct(
        public readonly int $applyId,
        public readonly int $guildId,
        public readonly int $playerId,
        public readonly string $status,
        public readonly string $updatedAt,
    ) {}

    /**
     * GuildApplyからレスポンスを生成
     */
    public static function fromDto(GuildApply $guildApply): self
    {
        return new self(
            applyId: $guildApply->getId(),
            guildId: $guildApply->getSysGuildId(),
            playerId: $guildApply->getSysPlayerId(),
            status: $guildApply->getStatus(),
            updatedAt: $guildApply->getUpdatedAt(),
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
            'sys_guild_apply_id' => $this->applyId,
            'sys_guild_id' => $this->guildId,
            'sys_player_id' => $this->playerId,
            'status' => $this->status,
            'updated_at' => $this->updatedAt,
        ];
    }
}
