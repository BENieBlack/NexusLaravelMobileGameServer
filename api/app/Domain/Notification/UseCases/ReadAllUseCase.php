<?php

namespace App\Domain\Notification\UseCases;

use App\Domain\_BaseUseCase;
use App\Http\Responses\Notification\ReadResponse;
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

    public function exec(int $sysPlayerId): ReadResponse
    {
        return $this->executeWithTransaction(function () use ($sysPlayerId) {
            $this->notificationService->markAllAsRead($sysPlayerId);

            return new ReadResponse(
                unreadCount: $this->notificationService->countUnread($sysPlayerId),
            );
        });
    }
}
