<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Notification\Jobs\SendNotificationJob;
use Illuminate\Contracts\Bus\Dispatcher;
use NexusGuild\Events\GuildApplySent;
use NexusNotification\Constants\NotificationType;

/**
 * NotifyOnGuildApplySent
 *
 * 加入申請を承認・却下できるマスターとサブマスターへ通知する
 */
class NotifyOnGuildApplySent
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function handle(GuildApplySent $event): void
    {
        foreach ($event->officerSysPlayerIds as $officerSysPlayerId) {
            $this->bus->dispatch(new SendNotificationJob(
                sysPlayerId: $officerSysPlayerId,
                type: NotificationType::GUILD_APPLY_RECEIVED,
                title: 'ギルドへの加入申請が届きました',
                body: 'ギルドへの加入申請が届きました。申請一覧から承認できます。',
                payload: [
                    'sys_guild_apply_id' => $event->sysGuildApplyId,
                    'sys_guild_id' => $event->sysGuildId,
                    'applicant_sys_player_id' => $event->applicantSysPlayerId,
                ],
            ));
        }
    }
}
