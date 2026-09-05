<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\_BaseRequest;

class MessagesRequest extends _BaseRequest
{
    private const DEFAULT_LIMIT = 30;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sys_chat_room_id' => ['required', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            // このメッセージIDより前を取得する（カーソルページネーション）
            'cursor' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function getSysChatRoomId(): int
    {
        return (int) $this->input('sys_chat_room_id');
    }

    public function getLimit(): int
    {
        return (int) $this->input('limit', self::DEFAULT_LIMIT);
    }

    public function getCursor(): ?int
    {
        $cursor = $this->input('cursor');

        return $cursor === null ? null : (int) $cursor;
    }
}
