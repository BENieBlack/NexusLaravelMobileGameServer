<?php

namespace Database\Seeders\Concerns;

/**
 * UsesShardConnections
 *
 * Trx / Log 系のシーダーが、全シャードの接続名（trx1, trx2, ...）を得るための共通処理。
 *
 * 接続名の一覧は config('database.pitr.active_trx_connections') から取る。これは DB_SHARD_COUNT から
 * 作られ、ShardAssignmentService（プレイヤーの割り当て先の候補）も同じ設定を見ている。
 * シーダーが接続名を ['trx1', 'trx2'] とハードコードすると、DB_SHARD_COUNT=3 の環境で
 * trx3 に割り当てられたプレイヤーが対象外になるため、必ずこの一覧を使う。
 */
trait UsesShardConnections
{
    /**
     * 全シャードの接続名を返す。
     *
     * @return list<string> 例: ['trx1', 'trx2', 'trx3']。設定が無ければ trx1 だけ
     */
    protected function shardConnections(): array
    {
        /** @var list<string> $connections */
        $connections = config('database.pitr.active_trx_connections', ['trx1']);

        return array_values($connections);
    }
}
