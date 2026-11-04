<?php

namespace App\Domain\Notification\Listeners;

use App\Domain\Notification\Jobs\SendGuildNotificationJob;
use Illuminate\Contracts\Bus\Dispatcher;
use NexusGuild\Events\GuildMemberLeft;
use NexusNotification\Constants\NotificationType;

/**
 * NotifyOnGuildMemberLeft
 *
 * ギルド全員へメンバーの脱退を流す
 */
class NotifyOnGuildMemberLeft
{
    public function __construct(
        private readonly Dispatcher $bus,
    ) {}

    public function handle(GuildMemberLeft $event): void
    {
        $this->bus->dispatch(new SendGuildNotificationJob(
            sysGuildId: $event->sysGuildId,
            type: NotificationType::GUILD_MEMBER_LEFT,
            title: 'メンバーが脱退しました',
            body: 'メンバーがギルドから脱退しました。',
            payload: [
                'sys_player_id' => $event->sysPlayerId,
            ],
        ));
    }
}
