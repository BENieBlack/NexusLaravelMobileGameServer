<?php

namespace App\Domain\Guild\Responses;

use App\Domain\_BaseResponse;

/**
 * LeaveResponse
 *
 * ギルド脱退APIのレスポンス
 */
class LeaveResponse extends _BaseResponse
{
    public function __construct(
        private readonly int $sysPlayerId,
    ) {}

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sys_player_id' => $this->sysPlayerId,
        ];
    }
}
