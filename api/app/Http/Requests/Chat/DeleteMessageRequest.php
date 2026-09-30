<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\_BaseRequest;

class DeleteMessageRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sys_chat_message_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function getSysChatMessageId(): int
    {
        return (int) $this->input('sys_chat_message_id');
    }
}
