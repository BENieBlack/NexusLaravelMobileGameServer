<?php

namespace App\Http\Requests\Notification;

use App\Http\Requests\_BaseRequest;

class ReadAllRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 全件既読に追加パラメータは不要
        ];
    }
}
