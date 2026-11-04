<?php

namespace App\Domain\Notification\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use NexusNotification\Constants\NotificationType;
use NexusNotification\Services\NotificationService;

/**
 * SendGuildNotificationJob
 *
 * ギルド全員へ通知をブロードキャストするジョブ（DBには保存しない）
 *
 * 加入・脱退が確定してから流すため、コミット後にキューへ積む。
 */
class SendGuildNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $sysGuildId,
        public readonly NotificationType $type,
        public readonly string $title,
        public readonly string $body,
        public readonly array $payload = [],
    ) {
        $this->afterCommit();
    }

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->notifyGuild(
            guildId: $this->sysGuildId,
            type: $this->type,
            title: $this->title,
            body: $this->body,
            payload: $this->payload,
        );
    }
}
