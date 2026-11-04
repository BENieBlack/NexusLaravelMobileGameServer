<?php

namespace App\Broadcasting;

use NexusNotification\Contracts\NotificationDispatcherInterface;
use NexusNotification\DataTransferObjects\Notification;

/**
 * ReverbNotificationDispatcher
 *
 * Laravel Reverb を使ったリアルタイム通知配送の実装。
 *
 * NotificationService はこの dispatch() を呼んだ後、
 * 受け取ったイベントディスパッチャへ NotificationCreated を発行する。
 * NotificationCreated は ShouldBroadcast を実装しているため、
 * Laravel が自動的に Reverb へブロードキャストする。
 *
 * このクラス自体はブロードキャストの追加設定を何も行わないが、
 * NotificationDispatcherInterface が bind されていることで
 * NotificationService が Dispatcher を持ち、
 * 発行後のブロードキャストが有効になる。
 */
class ReverbNotificationDispatcher implements NotificationDispatcherInterface
{
    /**
     * プレイヤーへリアルタイム通知を配送する
     *
     * ブロードキャストは NotificationCreated（ShouldBroadcast）が担うため、
     * ここでは何もしない。
     */
    public function dispatch(Notification $notification): void
    {
        // ブロードキャストは NotificationService が発行する
        // NotificationCreated（ShouldBroadcast）によって自動処理される
    }
}
