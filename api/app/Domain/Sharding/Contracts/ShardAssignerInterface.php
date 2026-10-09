<?php

namespace App\Domain\Sharding\Contracts;

/**
 * ShardAssignerInterface
 *
 * Shardingモジュールの公開API。新規プレイヤーをシャードへ割り当てる。
 */
interface ShardAssignerInterface
{
    /**
     * プレイヤーをシャードへ割り当てる
     *
     * @return int ノード番号（1始まり。trx1, trx2, ... に対応）
     *
     * @throws \RuntimeException 割り当て可能なノードが無い場合
     */
    public function assign(int $sysPlayerId): int;
}
