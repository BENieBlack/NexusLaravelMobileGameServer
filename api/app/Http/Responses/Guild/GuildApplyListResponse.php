<?php

namespace App\Http\Responses\Guild;

use App\Http\Responses\_BaseResponse;
use NexusGuild\Dto\GuildApplyDto;

/**
 * GuildApplyListResponse
 * 
 * ギルド加入申請一覧取得APIのレスポンス
 */
class GuildApplyListResponse extends _BaseResponse
{
    /**
     * @param array<array<string, mixed>> $applies
     */
    public function __construct(
        public readonly array $applies,
    ) {
    }

    /**
     * GuildApplyDto配列からレスポンスを生成
     *
     * @param array<GuildApplyDto> $applyDtos
     * @return self
     */
    public static function fromDtoArray(array $applyDtos): self
    {
        $applies = [];
        foreach ($applyDtos as $dto) {
            $applies[] = [
                'apply_id' => $dto->getId(),
                'guild_id' => $dto->getSysGuildId(),
                'player_id' => $dto->getSysPlayerId(),
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
            'applies' => $this->applies,
        ];
    }
}
