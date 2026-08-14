<?php

namespace NexusPitr\Migrations;

use NexusPitr\Logger\ShardMapper;

/**
 * DynamicShardingTrait
 * 
 * 動的シャーディング対応のマイグレーション用トレイト
 * DB_TRX_SHARDS環境変数に応じて全TrxDBシャードに対してマイグレーションを実行
 */
trait DynamicShardingTrait
{
    /**
     * 全TrxDBシャード接続名を取得
     * 
     * @return array<string>
     */
    protected function getTrxConnections(): array
    {
        return ShardMapper::allTrxConnections();
    }
}
