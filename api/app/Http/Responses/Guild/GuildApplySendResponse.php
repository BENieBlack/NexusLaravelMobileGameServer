<?php

namespace App\Http\Responses\Guild;

use App\Http\Responses\_BaseResponse;
use NexusGuild\DataTransferObjects\GuildApply;

/**
 * GuildApplySendResponse
 *
 * ギルド加入申請送信APIのレスポンス
 */
class GuildApplySendResponse extends _BaseResponse
{
    public function __construct(
        public readonly int $applyId,
        public readonly int $guildId,
        public readonly int $playerId,
        public readonly string $status,
        public readonly string $createdAt,
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
            createdAt: $guildApply->getCreatedAt(),
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
            'apply_id' => $this->applyId,
            'guild_id' => $this->guildId,
            'player_id' => $this->playerId,
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }
}
