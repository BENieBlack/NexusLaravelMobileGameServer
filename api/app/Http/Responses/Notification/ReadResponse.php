<?php

namespace App\Http\Responses\Notification;

use App\Http\Responses\_BaseResponse;

/**
 * ReadResponse
 *
 * 既読処理の結果。クライアントがバッジを描き直せるよう未読数を返す
 */
class ReadResponse extends _BaseResponse
{
    public function __construct(
        private readonly int $unreadCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'unread_count' => $this->unreadCount,
        ];
    }
}
