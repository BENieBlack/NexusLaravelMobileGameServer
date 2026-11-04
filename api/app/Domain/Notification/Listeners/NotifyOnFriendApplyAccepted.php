<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Notification\Jobs\SendNotificationJob;
use Illuminate\Contracts\Bus\Dispatcher;
use NexusFriend\Events\FriendApplyAccepted;
use NexusNotification\Constants\NotificationType;

/**
 * NotifyOnFriendApplyAccepted
 *
 * フレンド申請を送ったプレイヤーへ、承認されたことを通知する
 */
class NotifyOnFriendApplyAccepted
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function handle(FriendApplyAccepted $event): void
    {
        $this->bus->dispatch(new SendNotificationJob(
            sysPlayerId: $event->senderSysPlayerId,
            type: NotificationType::FRIEND_APPLY_ACCEPTED,
            title: 'フレンド申請が承認されました',
            body: 'フレンド申請が承認されました。',
            payload: [
                'sys_friend_apply_id' => $event->sysFriendApplyId,
                'receiver_sys_player_id' => $event->receiverSysPlayerId,
            ],
        ));
    }
}
