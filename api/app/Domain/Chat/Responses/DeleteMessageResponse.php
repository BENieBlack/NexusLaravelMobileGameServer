<?php

namespace App\Domain\Chat\Responses;

use App\Domain\_BaseResponse;

/**
 * DeleteMessageResponse
 *
 * メッセージ削除APIのレスポンス。返す値は無い
 */
class DeleteMessageResponse extends _BaseResponse
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [];
    }
}
