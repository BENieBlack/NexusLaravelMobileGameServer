<?php

namespace App\Http\Responses\Notification;

use App\Http\Responses\_BaseResponse;
use NexusNotification\DataTransferObjects\Notification;

/**
 * ListResponse
 *
 * 通知一覧
 */
class ListResponse extends _BaseResponse
{
    /**
     * @param  array<Notification>  $notifications
     */
    public function __construct(
        private readonly array $notifications,
        private readonly int $unreadCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'notifications' => array_map(
                fn (Notification $notification): array => [
                    'trx_notification_id' => $notification->getId(),
                    'type' => $notification->getType()->value,
                    'title' => $notification->getTitle(),
                    'body' => $notification->getBody(),
                    'payload' => $notification->getPayload(),
                    'is_read' => $notification->isRead(),
                    'read_at' => $notification->getReadAt(),
                    'created_at' => $notification->getCreatedAt(),
                ],
                $this->notifications
            ),
            'unread_count' => $this->unreadCount,
        ];
    }
}
