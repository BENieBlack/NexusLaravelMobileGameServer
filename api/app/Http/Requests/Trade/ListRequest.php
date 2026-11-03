<?php

namespace App\Http\Requests\Trade;

use App\Http\Requests\_BaseRequest;

/**
 * ListRequest
 *
 * GET /api/trade/list のリクエスト
 */
class ListRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 交換所の一覧取得には追加パラメータ不要
        ];
    }
}
