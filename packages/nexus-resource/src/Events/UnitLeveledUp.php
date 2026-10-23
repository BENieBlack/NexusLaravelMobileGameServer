<?php

namespace NexusResource\Events;

/**
 * UnitLeveledUp
 *
 * プレイヤーの所持ユニットのレベルが上がったことを表すドメインイベント
 *
 * 経験値の加算と同じトランザクション（UnitOfWork）の中で同期的に配信される。
 * 受け手の書き込みもレベルアップと一緒に確定するため、キューに積む受け手にはしないこと。
 */
class UnitLeveledUp
{
    public function __construct(
        public readonly int $sysPlayerId,
        public readonly int $trxUnitId,
        public readonly string $mstUnitId,
        public readonly int $beforeLevel,
        public readonly int $afterLevel,
    ) {}
}
