<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\_BaseRequest;

/**
 * ルームだけを取る操作（退室・メンバー一覧）の共通リクエスト
 */
class RoomRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sys_chat_room_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function getSysChatRoomId(): int
    {
        return (int) $this->input('sys_chat_room_id');
    }
}
