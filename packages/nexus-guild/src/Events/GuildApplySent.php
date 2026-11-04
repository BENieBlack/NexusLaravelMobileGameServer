<?php

namespace NexusGuild\Events;

/**
 * GuildApplySent
 *
 * ギルドへ加入申請を送ったことを表すドメインイベント
 *
 * 申請を承認・却下できるマスターとサブマスターのIDを載せる。
 * 受け手がギルドのメンバーを引き直さずに済むようにするため。
 */
class GuildApplySent
{
    /**
     * @param  list<int>  $officerSysPlayerIds  申請を承認・却下できるメンバー
     */
    public function __construct(
        public readonly int $sysGuildApplyId,
        public readonly int $sysGuildId,
        public readonly int $applicantSysPlayerId,
        public readonly array $officerSysPlayerIds,
    ) {}
}
