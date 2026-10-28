<?php

namespace App\Http\Responses\Agreement;

use App\Http\Responses\_BaseResponse;

/**
 * ConsentResponse
 *
 * POST /api/agreement/consent のレスポンス
 */
class ConsentResponse extends _BaseResponse
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [];
    }
}
