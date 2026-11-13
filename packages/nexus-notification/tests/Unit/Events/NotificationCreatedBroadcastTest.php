<?php

namespace NexusNotification\Tests\Unit\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use NexusNotification\Constants\NotificationType;
use NexusNotification\DataTransferObjects\Notification;
use NexusNotification\Events\NotificationCreated;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * NotificationCreatedイベントのブロードキャスト仕様を固定するUnitテスト
 *
 * クライアントへのリアルタイム配送に使うチャンネル名・イベント名・ペイロードを固定する。
 * クライアントが誤ったチャンネルを購読しているとメッセージが届かないため、
 * ここでサーバー仕様を明示的に固定しておく。
 */
class NotificationCreatedBroadcastTest extends TestCase
{
    #[Test]
    public function should_broadcastを実装している(): void
    {
        $event = new NotificationCreated($this->makeNotification(), 'AbCd1234');

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    #[Test]
    public function broadcast_on_は公開_i_d_my_idのプライベートチャンネルを返す(): void
    {
        $notification = $this->makeNotification(playerId: 42);
        $event = new NotificationCreated($notification, 'AbCd1234');

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        // PrivateChannel の name には自動的に "private-" プレフィックスが付く。
        // 内部の sys_player_id(42) ではなく公開 ID の my_id を使う
        $this->assertSame('private-notification.AbCd1234', $channels[0]->name);
        $this->assertStringNotContainsString('42', $channels[0]->name);
    }

    #[Test]
    public function broadcast_as_はnotification_createdを返す(): void
    {
        $event = new NotificationCreated($this->makeNotification(), 'AbCd1234');

        $this->assertSame('notification.created', $event->broadcastAs());
    }

    #[Test]
    public function broadcast_with_は通知の全フィールドを含む(): void
    {
        $notification = $this->makeNotification();
        $event = new NotificationCreated($notification, 'AbCd1234');

        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('body', $payload);
        $this->assertArrayHasKey('payload', $payload);
        $this->assertArrayHasKey('created_at', $payload);

        $this->assertSame(1, $payload['id']);
        $this->assertSame('フレンド申請', $payload['title']);
        $this->assertSame('たろうさんから申請が届きました', $payload['body']);
        $this->assertSame(['friend_apply_id' => 9], $payload['payload']);
        $this->assertSame('2026-09-05 00:00:00', $payload['created_at']);
    }

    #[Test]
    public function broadcast_with_typeはenumのvalueである文字列(): void
    {
        // NotificationType::MISSION_COMPLETED → "mission_completed" が返ることを固定する
        $notification = $this->makeNotification(type: NotificationType::MISSION_COMPLETED);
        $event = new NotificationCreated($notification, 'AbCd1234');

        $payload = $event->broadcastWith();

        $this->assertSame('mission_completed', $payload['type']);
        $this->assertIsString($payload['type']);
    }

    private function makeNotification(
        int $id = 1,
        int $playerId = 1,
        NotificationType $type = NotificationType::FRIEND_APPLY_RECEIVED,
    ): Notification {
        return new Notification(
            id: $id,
            playerId: $playerId,
            type: $type,
            title: 'フレンド申請',
            body: 'たろうさんから申請が届きました',
            payload: ['friend_apply_id' => 9],
            isRead: false,
            readAt: null,
            createdAt: '2026-09-05 00:00:00',
        );
    }
}
