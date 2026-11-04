<?php

namespace App\Domain\Notification\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use NexusNotification\Constants\NotificationType;
use NexusNotification\Services\NotificationService;

/**
 * SendNotificationJob
 *
 * プレイヤーへ通知を保存し、Reverb へブロードキャストするジョブ
 *
 * 通知の受け手は送り手と別のシャードにいることがあり、送り手のトランザクションには
 * 含められない。送り手の処理が確定してから送るため、コミット後にキューへ積む。
 * ロールバックされた申請の通知は送られない。
 */
class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $sysPlayerId,
        public readonly NotificationType $type,
        public readonly string $title,
        public readonly string $body,
        public readonly array $payload = [],
    ) {
        $this->afterCommit();
    }

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->notify(
            playerId: $this->sysPlayerId,
            type: $this->type,
            title: $this->title,
            body: $this->body,
            payload: $this->payload,
        );
    }
}
