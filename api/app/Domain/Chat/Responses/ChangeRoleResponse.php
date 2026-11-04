<?php

namespace App\Domain\Chat\Responses;

use App\Domain\_BaseResponse;

/**
 * ChangeRoleResponse
 *
 * グループチャットのロール変更APIのレスポンス。返す値は無い
 */
class ChangeRoleResponse extends _BaseResponse
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [];
    }
}
