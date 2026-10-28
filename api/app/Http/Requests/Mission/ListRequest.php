<?php

namespace App\Http\Requests\Mission;

use App\Http\Requests\_BaseRequest;

class ListRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // ミッション一覧取得には追加パラメータ不要
        ];
    }
}
