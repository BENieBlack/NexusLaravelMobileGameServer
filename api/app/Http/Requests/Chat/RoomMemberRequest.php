<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\_BaseRequest;

/**
 * ルームと対象プレイヤーを取る操作（招待・キック）の共通リクエスト
 */
class RoomMemberRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sys_chat_room_id' => ['required', 'integer', 'min:1'],
            'sys_player_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function getSysChatRoomId(): int
    {
        return (int) $this->input('sys_chat_room_id');
    }

    public function getSysPlayerId(): int
    {
        return (int) $this->input('sys_player_id');
    }
}
