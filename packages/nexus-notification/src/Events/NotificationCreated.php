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
 */
class NotificationCreated implements ShouldBroadcast
{
    public function __construct(
        public readonly Notification $notification,
    ) {}

    /**
     * ブロードキャスト先チャンネル
     *
     * プライベートチャンネル: private-notification.{sys_player_id}
     *
     * @return array<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('notification.' . $this->notification->getPlayerId())];
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
            'id'         => $this->notification->getId(),
            'type'       => $this->notification->getType()->value,
            'title'      => $this->notification->getTitle(),
            'body'       => $this->notification->getBody(),
            'payload'    => $this->notification->getPayload(),
            'created_at' => $this->notification->getCreatedAt(),
        ];
    }
}
