<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\_BaseRequest;

class SendMessageRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sys_chat_room_id' => ['required', 'integer', 'min:1'],
            // 文字数の上限はパッケージ側（500文字）で判定する。
            // ここで弾くと上限が2箇所に散る
            'body' => ['required', 'string'],
        ];
    }

    public function getSysChatRoomId(): int
    {
        return (int) $this->input('sys_chat_room_id');
    }

    public function getBody(): string
    {
        return (string) $this->input('body');
    }
}
