<?php

namespace App\Domain\Notification\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Notification\Responses\ReadResponse;
use NexusNotification\Services\NotificationService;

/**
 * ReadUseCase
 *
 * 通知1件の既読化
 *
 * 他人の通知やIDの総当たりは Service 側で弾かれ、何も起きない。
 * 存在しないIDを渡された場合も同じ（エラーにはしない）。
 */
class ReadUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(int $sysPlayerId, int $trxNotificationId): ReadResponse
    {
        $unreadCount = $this->executeWithTransaction(function () use ($sysPlayerId, $trxNotificationId) {
            $this->notificationService->markAsRead($trxNotificationId, $sysPlayerId);

            return $this->notificationService->countUnread($sysPlayerId);
        });

        return ReadResponse::from($unreadCount);
    }
}
