<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\_BaseRequest;
use NexusChat\Constants\ChatRoomRole;

class ChangeRoleRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sys_chat_room_id' => ['required', 'integer', 'min:1'],
            'sys_player_id' => ['required', 'integer', 'min:1'],
            'role' => ['required', 'string', 'in:'.implode(',', array_column(ChatRoomRole::cases(), 'value'))],
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

    public function getRole(): ChatRoomRole
    {
        return ChatRoomRole::from((string) $this->input('role'));
    }
}
