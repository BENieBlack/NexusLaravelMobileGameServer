<?php

namespace NexusPitr\Logger;

/**
 * ShardMapper
 *
 * TrxDB接続とLogDB接続のマッピングを管理
 * 動的シャーディング対応（DB_SHARD_COUNT環境変数でシャード数を制御）
 */
class ShardMapper
{
    /**
     * TrxDB接続名から対応するLogDB接続名を取得
     *
     * @param  string  $trxConnection
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public static function resolveLogConnection(string $trxConnection): string
    {
        // 動的シャーディング: trx1 -> log1, trx2 -> log2, ...
        if (preg_match('/^trx(\d+)$/', $trxConnection, $matches)) {
            $shardNumber = (int) $matches[1];
            $maxShards = self::getMaxShardCount();

            if ($shardNumber >= 1 && $shardNumber <= $maxShards) {
                return "log{$shardNumber}";
            }
        }

        throw new \InvalidArgumentException("Unknown trx connection: {$trxConnection}");
    }

    /**
     * LogDB接続名から対応するTrxDB接続名を取得
     *
     * @param  string  $logConnection
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public static function resolveTrxConnection(string $logConnection): string
    {
        // 動的シャーディング: log1 -> trx1, log2 -> trx2, ...
        if (preg_match('/^log(\d+)$/', $logConnection, $matches)) {
            $shardNumber = (int) $matches[1];
            $maxShards = self::getMaxShardCount();

            if ($shardNumber >= 1 && $shardNumber <= $maxShards) {
                return "trx{$shardNumber}";
            }
        }

        throw new \InvalidArgumentException("Unknown log connection: {$logConnection}");
    }

    /**
     * すべてのLogDB接続名を取得
     *
     * @return array<string>
     */
    public static function allLogConnections(): array
    {
        $maxShards = self::getMaxShardCount();
        $connections = [];

        for ($i = 1; $i <= $maxShards; $i++) {
            $connections[] = "log{$i}";
        }

        return $connections;
    }

    /**
     * すべてのTrxDB接続名を取得
     *
     * @return array<string>
     */
    public static function allTrxConnections(): array
    {
        $maxShards = self::getMaxShardCount();
        $connections = [];

        for ($i = 1; $i <= $maxShards; $i++) {
            $connections[] = "trx{$i}";
        }

        return $connections;
    }

    /**
     * 指定されたTrxDB接続が有効かチェック
     *
     * @param  string  $trxConnection
     * @return bool
     */
    public static function isValidTrxConnection(string $trxConnection): bool
    {
        // 動的シャーディング
        if (preg_match('/^trx(\d+)$/', $trxConnection, $matches)) {
            $shardNumber = (int) $matches[1];
            $maxShards = self::getMaxShardCount();

            return $shardNumber >= 1 && $shardNumber <= $maxShards;
        }

        return false;
    }

    /**
     * 指定されたLogDB接続が有効かチェック
     *
     * @param  string  $logConnection
     * @return bool
     */
    public static function isValidLogConnection(string $logConnection): bool
    {
        // 動的シャーディング
        if (preg_match('/^log(\d+)$/', $logConnection, $matches)) {
            $shardNumber = (int) $matches[1];
            $maxShards = self::getMaxShardCount();

            return $shardNumber >= 1 && $shardNumber <= $maxShards;
        }

        return false;
    }

    /**
     * 最大シャード数を取得
     *
     * @return int
     */
    private static function getMaxShardCount(): int
    {
        return (int) (getenv('DB_SHARD_COUNT') ?: 2);
    }
}
