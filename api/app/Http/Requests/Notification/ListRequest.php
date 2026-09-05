<?php

namespace App\Http\Requests\Notification;

use App\Http\Requests\_BaseRequest;

class ListRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'only_unread' => ['sometimes', 'boolean'],
        ];
    }

    public function onlyUnread(): bool
    {
        return $this->boolean('only_unread');
    }
}
