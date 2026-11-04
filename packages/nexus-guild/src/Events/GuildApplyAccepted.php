<?php

namespace NexusGuild\Events;

/**
 * GuildApplyAccepted
 *
 * ギルドへの加入申請が承認され、申請者がメンバーになったことを表すドメインイベント
 */
class GuildApplyAccepted
{
    public function __construct(
        public readonly int $sysGuildApplyId,
        public readonly int $sysGuildId,
        public readonly int $applicantSysPlayerId,
    ) {}
}
