<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\_BaseRequest;

/**
 * ギルドチャットのルーム取得。所属ギルドはサーバ側で解決する
 */
class GuildRoomRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
