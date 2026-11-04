<?php

namespace NexusGuild\Events;

/**
 * GuildMemberLeft
 *
 * メンバーがギルドから脱退したことを表すドメインイベント
 */
class GuildMemberLeft
{
    public function __construct(
        public readonly int $sysGuildId,
        public readonly int $sysPlayerId,
    ) {}
}
