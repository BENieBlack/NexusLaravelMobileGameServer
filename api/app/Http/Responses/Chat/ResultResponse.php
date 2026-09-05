<?php

namespace App\Http\Responses\Chat;

use App\Http\Responses\_BaseResponse;

/**
 * ResultResponse
 *
 * 退室・キック・削除など、返す値が無い操作の結果
 */
class ResultResponse extends _BaseResponse
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['success' => true];
    }
}
