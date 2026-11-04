<?php

namespace App\Http\Responses\Guild;

use App\Http\Responses\_BaseResponse;

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
