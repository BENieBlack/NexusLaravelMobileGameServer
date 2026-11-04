<?php

namespace App\Domain\Notification\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Notification\Responses\ReadAllResponse;
use NexusNotification\Services\NotificationService;

/**
 * ReadAllUseCase
 *
 * プレイヤーの通知を全件既読にする
 */
class ReadAllUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(int $sysPlayerId): ReadAllResponse
    {
        return ReadAllResponse::fromResult($this->executeWithTransaction(function () use ($sysPlayerId) {
            $this->notificationService->markAllAsRead($sysPlayerId);

            return $this->notificationService->countUnread($sysPlayerId);
        }));
    }
}
