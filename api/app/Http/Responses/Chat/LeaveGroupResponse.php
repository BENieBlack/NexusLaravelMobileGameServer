<?php

namespace App\Http\Responses\Chat;

use App\Http\Responses\_BaseResponse;

/**
 * LeaveGroupResponse
 *
 * グループチャット退室APIのレスポンス。返す値は無い
 */
class LeaveGroupResponse extends _BaseResponse
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [];
    }
}
