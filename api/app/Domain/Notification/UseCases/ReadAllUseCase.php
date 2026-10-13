<?php

namespace App\Domain\Notification\UseCases;

use App\Domain\_BaseUseCase;
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

    /**
     * @return int 既読処理後の未読数
     */
    public function exec(int $sysPlayerId): int
    {
        return $this->executeWithTransaction(function () use ($sysPlayerId) {
            $this->notificationService->markAllAsRead($sysPlayerId);

            return $this->notificationService->countUnread($sysPlayerId);
        });
    }
}
