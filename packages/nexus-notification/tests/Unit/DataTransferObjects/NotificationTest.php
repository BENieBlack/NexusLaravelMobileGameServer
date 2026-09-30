<?php

namespace NexusNotification\Tests\Unit\DataTransferObjects;

use NexusNotification\Constants\NotificationType;
use NexusNotification\DataTransferObjects\Notification;
use NexusNotification\Events\NotificationCreated;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 通知のDTOと種別定数のテスト
 */
class NotificationTest extends TestCase
{
    #[Test]
    public function 通知の各値を取り出せる(): void
    {
        $notification = new Notification(
            id: 5,
            playerId: 100,
            type: NotificationType::GUILD_APPLY_RECEIVED,
            title: 'ギルド申請',
            body: 'たろうさんから申請が届きました',
            payload: ['guild_apply_id' => 9],
            isRead: false,
            readAt: null,
            createdAt: '2026-09-05 00:00:00',
        );

        $this->assertSame(5, $notification->getId());
        $this->assertSame(100, $notification->getPlayerId());
        $this->assertSame(NotificationType::GUILD_APPLY_RECEIVED, $notification->getType());
        $this->assertSame('ギルド申請', $notification->getTitle());
        $this->assertSame('たろうさんから申請が届きました', $notification->getBody());
        $this->assertSame(['guild_apply_id' => 9], $notification->getPayload());
        $this->assertFalse($notification->isRead());
        $this->assertNull($notification->getReadAt());
        $this->assertSame('2026-09-05 00:00:00', $notification->getCreatedAt());
    }

    #[Test]
    public function 既読の通知は既読時刻を持つ(): void
    {
        $notification = new Notification(
            id: 5,
            playerId: 100,
            type: NotificationType::MAILBOX_RECEIVED,
            title: 'メール',
            body: '受け取ってください',
            payload: [],
            isRead: true,
            readAt: '2026-09-05 01:00:00',
            createdAt: '2026-09-05 00:00:00',
        );

        $this->assertTrue($notification->isRead());
        $this->assertSame('2026-09-05 01:00:00', $notification->getReadAt());
    }

    #[Test]
    public function イベントは通知を持ち回る(): void
    {
        $notification = new Notification(
            id: 1,
            playerId: 1,
            type: NotificationType::LOGIN_BONUS_READY,
            title: 'ログインボーナス',
            body: '受け取れます',
            payload: [],
            isRead: false,
            readAt: null,
            createdAt: '2026-09-05 00:00:00',
        );

        $this->assertSame($notification, (new NotificationCreated($notification))->notification);
    }

    #[Test]
    public function 通知種別の文字列表現はデータベースに入る値と一致する(): void
    {
        // 種別はDBのカラムに文字列で入り、クライアントの分岐にも使われる
        $this->assertSame('mission_completed', NotificationType::MISSION_COMPLETED->value);
        $this->assertSame('friend_apply_received', NotificationType::FRIEND_APPLY_RECEIVED->value);
        $this->assertSame('guild_apply_accepted', NotificationType::GUILD_APPLY_ACCEPTED->value);
        $this->assertSame('mailbox_received', NotificationType::MAILBOX_RECEIVED->value);
        $this->assertSame('system_announcement', NotificationType::SYSTEM_ANNOUNCEMENT->value);
    }
}
