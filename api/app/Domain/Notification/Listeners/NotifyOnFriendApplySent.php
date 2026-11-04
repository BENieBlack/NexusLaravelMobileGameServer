<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Notification\Jobs\SendNotificationJob;
use Illuminate\Contracts\Bus\Dispatcher;
use NexusFriend\Events\FriendApplySent;
use NexusNotification\Constants\NotificationType;

/**
 * NotifyOnFriendApplySent
 *
 * フレンド申請を受け取ったプレイヤーへ通知する
 */
class NotifyOnFriendApplySent
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function handle(FriendApplySent $event): void
    {
        $this->bus->dispatch(new SendNotificationJob(
            sysPlayerId: $event->receiverSysPlayerId,
            type: NotificationType::FRIEND_APPLY_RECEIVED,
            title: 'フレンド申請が届きました',
            body: 'フレンド申請が届きました。申請一覧から承認できます。',
            payload: [
                'sys_friend_apply_id' => $event->sysFriendApplyId,
                'sender_sys_player_id' => $event->senderSysPlayerId,
            ],
        ));
    }
}
