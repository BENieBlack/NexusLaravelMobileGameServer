<?php

namespace App\Domain\Agreement\Responses;

use App\Domain\_BaseResponse;

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
