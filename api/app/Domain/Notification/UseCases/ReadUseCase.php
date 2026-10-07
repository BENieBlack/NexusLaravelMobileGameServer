<?php

namespace App\Domain\Notification\UseCases;

use App\Domain\_BaseUseCase;
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

    /**
     * @return int 既読処理後の未読数
     */
    public function exec(int $sysPlayerId, int $trxNotificationId): int
    {
        return $this->executeWithTransaction(function () use ($sysPlayerId, $trxNotificationId) {
            $this->notificationService->markAsRead($trxNotificationId, $sysPlayerId);

            return $this->notificationService->countUnread($sysPlayerId);
        });
    }
}
