<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\_BaseRequest;

/**
 * フレンドDMのルーム取得。相手のプレイヤーIDを受け取る
 * （自分は認証トークンから解決する）
 */
class FriendRoomRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sys_player_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function getSysPlayerId(): int
    {
        return (int) $this->input('sys_player_id');
    }
}
