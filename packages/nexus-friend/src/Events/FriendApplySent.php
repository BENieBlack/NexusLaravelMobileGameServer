<?php

namespace NexusFriend\Events;

/**
 * FriendApplySent
 *
 * フレンド申請を送ったことを表すドメインイベント
 *
 * 申請の作成と同じトランザクションの中で同期的に配信される。
 * 受け手が別のプレイヤー（別のシャード）へ書く場合は、コミット後に動くキューへ渡すこと。
 */
class FriendApplySent
{
    public function __construct(
        public readonly int $sysFriendApplyId,
        public readonly int $senderSysPlayerId,
        public readonly int $receiverSysPlayerId,
    ) {}
}
