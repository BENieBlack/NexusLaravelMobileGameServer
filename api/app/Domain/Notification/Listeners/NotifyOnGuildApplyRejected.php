<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Notification\Jobs\SendNotificationJob;
use Illuminate\Contracts\Bus\Dispatcher;
use NexusGuild\Events\GuildApplyRejected;
use NexusNotification\Constants\NotificationType;

/**
 * NotifyOnGuildApplyRejected
 *
 * 申請者へ加入申請が却下されたことを通知する
 */
class NotifyOnGuildApplyRejected
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function handle(GuildApplyRejected $event): void
    {
        $this->bus->dispatch(new SendNotificationJob(
            sysPlayerId: $event->applicantSysPlayerId,
            type: NotificationType::GUILD_APPLY_REJECTED,
            title: 'ギルドへの加入申請が却下されました',
            body: 'ギルドへの加入申請が却下されました。',
            payload: [
                'sys_guild_apply_id' => $event->sysGuildApplyId,
                'sys_guild_id' => $event->sysGuildId,
            ],
        ));
    }
}
