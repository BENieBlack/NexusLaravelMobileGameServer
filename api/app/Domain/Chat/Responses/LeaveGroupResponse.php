<?php

namespace App\Domain\Chat\Responses;

use App\Domain\_BaseResponse;

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
