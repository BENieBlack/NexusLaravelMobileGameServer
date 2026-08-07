<?php

namespace App\Http\Responses\Guild;

use App\Http\Responses\_BaseResponse;
use NexusGuild\Dto\GuildApplyDto;

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
    ) {
    }

    /**
     * GuildApplyDtoからレスポンスを生成
     *
     * @param GuildApplyDto $applyDto
     * @return self
     */
    public static function fromDto(GuildApplyDto $applyDto): self
    {
        return new self(
            applyId: $applyDto->getId(),
            guildId: $applyDto->getSysGuildId(),
            playerId: $applyDto->getSysPlayerId(),
            status: $applyDto->getStatus(),
            createdAt: $applyDto->getCreatedAt(),
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
