<?php

namespace App\Http\Responses\Chat;

use App\Http\Responses\_BaseResponse;

/**
 * KickResponse
 *
 * グループチャットからのキックAPIのレスポンス。返す値は無い
 */
class KickResponse extends _BaseResponse
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [];
    }
}
