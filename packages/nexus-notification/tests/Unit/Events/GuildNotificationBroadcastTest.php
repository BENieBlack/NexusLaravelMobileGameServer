<?php

namespace NexusNotification\Tests\Unit\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use NexusNotification\Constants\NotificationType;
use NexusNotification\DataTransferObjects\Notification;
use NexusNotification\Events\GuildNotificationBroadcast;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * GuildNotificationBroadcastイベントのブロードキャスト仕様を固定するUnitテスト
 *
 * クライアントへのリアルタイム配送に使うチャンネル名・イベント名・ペイロードを固定する。
 * クライアントが誤ったチャンネルを購読しているとメッセージが届かないため、
 * ここでサーバー仕様を明示的に固定しておく。
 */
class GuildNotificationBroadcastTest extends TestCase
{
    #[Test]
    public function should_broadcastを実装している(): void
    {
        $event = new GuildNotificationBroadcast(1, $this->makeNotification());

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    #[Test]
    public function broadcast_on_はギルド_i_dを含む_presence_channelを返す(): void
    {
        $event = new GuildNotificationBroadcast(guildId: 42, notification: $this->makeNotification());

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PresenceChannel::class, $channels[0]);
        // PresenceChannel の name には自動的に "presence-" プレフィックスが付く
        $this->assertSame('presence-guild.42', $channels[0]->name);
    }

    #[Test]
    public function broadcast_as_はguild_notificationを返す(): void
    {
        $event = new GuildNotificationBroadcast(1, $this->makeNotification());

        $this->assertSame('guild.notification', $event->broadcastAs());
    }

    #[Test]
    public function broadcast_with_は通知の全フィールドを含む(): void
    {
        $notification = $this->makeNotification();
        $event = new GuildNotificationBroadcast(1, $notification);

        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('title', $payload);
        $this->assertArrayHasKey('body', $payload);
        $this->assertArrayHasKey('payload', $payload);
        $this->assertArrayHasKey('created_at', $payload);

        $this->assertSame(0, $payload['id']);
        $this->assertSame('ギルドメンバーが加入しました', $payload['title']);
        $this->assertSame('たろうさんがギルドに加入しました', $payload['body']);
        $this->assertSame(['guild_id' => 1], $payload['payload']);
        $this->assertSame('2026-09-05 00:00:00', $payload['created_at']);
    }

    #[Test]
    public function broadcast_with_typeはenumのvalueである文字列(): void
    {
        // NotificationType::GUILD_MEMBER_JOINED → "guild_member_joined" が返ることを固定する
        $notification = $this->makeNotification(type: NotificationType::GUILD_MEMBER_JOINED);
        $event = new GuildNotificationBroadcast(1, $notification);

        $payload = $event->broadcastWith();

        $this->assertSame('guild_member_joined', $payload['type']);
        $this->assertIsString($payload['type']);
    }

    private function makeNotification(
        NotificationType $type = NotificationType::GUILD_MEMBER_JOINED,
    ): Notification {
        return new Notification(
            id: 0,
            playerId: 0,
            type: $type,
            title: 'ギルドメンバーが加入しました',
            body: 'たろうさんがギルドに加入しました',
            payload: ['guild_id' => 1],
            isRead: false,
            readAt: null,
            createdAt: '2026-09-05 00:00:00',
        );
    }
}
