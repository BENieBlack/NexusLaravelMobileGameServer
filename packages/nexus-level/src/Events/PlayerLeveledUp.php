<?php

namespace NexusLevel\Events;

/**
 * PlayerLeveledUp
 *
 * プレイヤーのレベルが上がったことを表すドメインイベント
 *
 * 経験値の加算と同じトランザクション（UnitOfWork）の中で同期的に配信される。
 * 受け手の書き込みもレベルアップと一緒に確定するため、キューに積む受け手にはしないこと。
 */
class PlayerLeveledUp
{
    public function __construct(
        public readonly int $sysPlayerId,
        public readonly int $beforeLevel,
        public readonly int $afterLevel,
    ) {}
}
