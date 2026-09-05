<?php

namespace App\Http\Requests\Notification;

use App\Http\Requests\_BaseRequest;

class ReadRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trx_notification_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function getTrxNotificationId(): int
    {
        return (int) $this->input('trx_notification_id');
    }
}
