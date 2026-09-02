<?php

namespace NexusNotification\Events;

use NexusNotification\DataTransferObjects\Notification;

/**
 * NotificationCreated
 *
 * 通知が作成された時に発火するドメインイベント
 * リアルタイム配送のトリガーとして使用する
 */
class NotificationCreated
{
    public function __construct(
        public readonly Notification $notification,
    ) {}
}
