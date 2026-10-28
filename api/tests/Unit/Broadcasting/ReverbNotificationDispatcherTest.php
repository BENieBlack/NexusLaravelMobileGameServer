<?php

namespace Tests\Unit\Broadcasting;

use App\Broadcasting\ReverbNotificationDispatcher;
use NexusNotification\Constants\NotificationType;
use NexusNotification\Contracts\NotificationDispatcherInterface;
use NexusNotification\DataTransferObjects\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ReverbNotificationDispatcherのUnitテスト
 *
 * このクラスは NotificationDispatcherInterface の実装であるが、
 * ブロードキャスト自体は event(new NotificationCreated($notification)) が担うため
 * dispatch() は何もしない実装となっている。
 * インターフェース準拠と正常終了の2点を固定する。
 */
class ReverbNotificationDispatcherTest extends TestCase
{
    #[Test]
    public function dispatch_はNotificationDispatcherInterfaceを実装している(): void
    {
        $dispatcher = new ReverbNotificationDispatcher;

        $this->assertInstanceOf(NotificationDispatcherInterface::class, $dispatcher);
    }

    #[Test]
    public function dispatch_を呼んでも例外が発生しない(): void
    {
        // dispatch() は何もしない実装のため、例外なく正常終了することを確認する
        $dispatcher = new ReverbNotificationDispatcher;
        $notification = new Notification(
            id: 1,
            playerId: 1,
            type: NotificationType::FRIEND_APPLY_RECEIVED,
            title: 'フレンド申請',
            body: 'たろうさんから申請が届きました',
            payload: [],
            isRead: false,
            readAt: null,
            createdAt: '2026-09-05 00:00:00',
        );

        $dispatcher->dispatch($notification);

        // 例外が発生せず到達したことを確認
        $this->assertTrue(true);
    }
}
