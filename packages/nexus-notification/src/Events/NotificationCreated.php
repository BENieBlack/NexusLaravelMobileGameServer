<?php

namespace NexusNotification\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use NexusNotification\DataTransferObjects\Notification;

/**
 * NotificationCreated
 *
 * 通知が作成された時に発火するドメインイベント。
 * ShouldBroadcast を実装しているため、Laravel が自動的に Reverb へブロードキャストする。
 * 配信先チャンネルの名前に使う my_id は、呼び出し側（NotificationService）が受け手から引いて渡す。
 */
class NotificationCreated implements ShouldBroadcast
{
    public function __construct(
        public readonly Notification $notification,
        public readonly string $recipientMyId,
    ) {}

    /**
     * ブロードキャスト先チャンネル
     *
     * プライベートチャンネル: private-notification.{my_id}
     * チャンネル名には受け手の公開 ID（my_id）を使い、内部の sys_player_id は露出しない
     *
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('notification.'.$this->recipientMyId)];
    }

    /**
     * ブロードキャストイベント名
     */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * ブロードキャストペイロード
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->getId(),
            'type' => $this->notification->getType()->value,
            'title' => $this->notification->getTitle(),
            'body' => $this->notification->getBody(),
            'payload' => $this->notification->getPayload(),
            'created_at' => $this->notification->getCreatedAt(),
        ];
    }
}
