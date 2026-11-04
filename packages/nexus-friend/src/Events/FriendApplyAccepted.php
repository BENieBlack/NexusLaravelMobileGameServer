<?php

namespace NexusFriend\Events;

/**
 * FriendApplyAccepted
 *
 * フレンド申請が承認されたことを表すドメインイベント
 *
 * 承認と同じトランザクションの中で同期的に配信される。
 * 受け手が別のプレイヤー（別のシャード）へ書く場合は、コミット後に動くキューへ渡すこと。
 */
class FriendApplyAccepted
{
    public function __construct(
        public readonly int $sysFriendApplyId,
        public readonly int $senderSysPlayerId,
        public readonly int $receiverSysPlayerId,
    ) {}
}
