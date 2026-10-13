<?php

namespace App\Domain\Notification\DataTransferObjects;

use NexusNotification\DataTransferObjects\Notification;

/**
 * ListResult
 *
 * 通知一覧の取得結果
 */
class ListResult
{
    /**
     * @param  array<Notification>  $notifications
     */
    public function __construct(
        public readonly array $notifications,
        public readonly int $unreadCount,
    ) {}
}
