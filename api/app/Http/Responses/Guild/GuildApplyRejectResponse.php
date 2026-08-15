<?php

namespace App\Http\Responses\Guild;

use App\Http\Responses\_BaseResponse;
use NexusGuild\DataTransferObjects\GuildApply;

/**
 * GuildApplyRejectResponse
 *
 * ギルド加入申請却下APIのレスポンス
 */
class GuildApplyRejectResponse extends _BaseResponse
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
    public static function fromDto(GuildApply $guildApplyDto): self
    {
        return new self(
            applyId: $guildApplyDto->getId(),
            guildId: $guildApplyDto->getSysGuildId(),
            playerId: $guildApplyDto->getSysPlayerId(),
            status: $guildApplyDto->getStatus(),
            updatedAt: $guildApplyDto->getUpdatedAt(),
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
            'updated_at' => $this->updatedAt,
        ];
    }
}
