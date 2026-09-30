<?php

namespace App\Domain\Notification\UseCases;

use App\Domain\_BaseUseCase;
use App\Http\Responses\Notification\ListResponse;
use NexusNotification\Services\NotificationService;

/**
 * ListUseCase
 *
 * 通知一覧の取得
 */
class ListUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function exec(int $sysPlayerId, bool $onlyUnread = false): ListResponse
    {
        return new ListResponse(
            notifications: $this->notificationService->findByPlayer($sysPlayerId, $onlyUnread),
            unreadCount: $this->notificationService->countUnread($sysPlayerId),
        );
    }
}
