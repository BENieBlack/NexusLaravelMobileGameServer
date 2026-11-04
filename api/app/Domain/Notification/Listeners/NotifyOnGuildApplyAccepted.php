<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Notification\Jobs\SendGuildNotificationJob;
use App\Domain\Notification\Jobs\SendNotificationJob;
use Illuminate\Contracts\Bus\Dispatcher;
use NexusGuild\Events\GuildApplyAccepted;
use NexusNotification\Constants\NotificationType;

/**
 * NotifyOnGuildApplyAccepted
 *
 * 申請者へ承認されたことを通知し、ギルド全員へ新しいメンバーの加入を流す
 */
class NotifyOnGuildApplyAccepted
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function handle(GuildApplyAccepted $event): void
    {
        $this->bus->dispatch(new SendNotificationJob(
            sysPlayerId: $event->applicantSysPlayerId,
            type: NotificationType::GUILD_APPLY_ACCEPTED,
            title: 'ギルドへの加入申請が承認されました',
            body: 'ギルドへの加入申請が承認されました。',
            payload: [
                'sys_guild_apply_id' => $event->sysGuildApplyId,
                'sys_guild_id' => $event->sysGuildId,
            ],
        ));

        $this->bus->dispatch(new SendGuildNotificationJob(
            sysGuildId: $event->sysGuildId,
            type: NotificationType::GUILD_MEMBER_JOINED,
            title: '新しいメンバーが加入しました',
            body: '新しいメンバーがギルドに加入しました。',
            payload: [
                'sys_player_id' => $event->applicantSysPlayerId,
            ],
        ));
    }
}
