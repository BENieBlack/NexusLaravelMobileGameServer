<?php

namespace NexusNotification\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use NexusNotification\DataTransferObjects\Notification;

/**
 * GuildNotificationBroadcast
 *
 * ギルド全員向け通知が発生した時に発火するドメインイベント。
 * ShouldBroadcast を実装しているため、Laravel が自動的に Reverb へブロードキャストする。
 * チャンネルは presence-guild.{guild_id} を使用する。
 */
class GuildNotificationBroadcast implements ShouldBroadcast
{
    public function __construct(
        public readonly int $guildId,
        public readonly Notification $notification,
    ) {}

    /**
     * ブロードキャスト先チャンネル
     *
     * プレゼンスチャンネル: presence-guild.{guild_id}
     *
     * @return array<PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('guild.' . $this->guildId)];
    }

    /**
     * ブロードキャストイベント名
     */
    public function broadcastAs(): string
    {
        return 'guild.notification';
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
