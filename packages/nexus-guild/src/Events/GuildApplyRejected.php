<?php

namespace NexusGuild\Events;

/**
 * GuildApplyRejected
 *
 * ギルドへの加入申請が却下されたことを表すドメインイベント
 */
class GuildApplyRejected
{
    public function __construct(
        public readonly int $sysGuildApplyId,
        public readonly int $sysGuildId,
        public readonly int $applicantSysPlayerId,
    ) {}
}
