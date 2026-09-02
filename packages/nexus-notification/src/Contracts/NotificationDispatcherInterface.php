<?php

namespace NexusNotification\Contracts;

use NexusNotification\DataTransferObjects\Notification;

/**
 * NotificationDispatcherInterface
 *
 * リアルタイム配送（WebSocket等）のインターフェース
 * Application層がLaravel Reverbを使って実装する
 */
interface NotificationDispatcherInterface
{
    /**
     * プレイヤーへリアルタイム通知を配送する
     *
     * @param  Notification  $notification  配送する通知
     */
    public function dispatch(Notification $notification): void;
}
