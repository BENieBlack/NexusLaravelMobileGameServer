<?php

namespace NexusPitr\Logger;

/**
 * ShardMapper
 * 
 * TrxDB接続とLogDB接続のマッピングを管理
 */
class ShardMapper
{
    /**
     * TrxDB接続名から対応するLogDB接続名を取得
     * 
     * @param string $trxConnection
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function getLogConnection(string $trxConnection): string
    {
        return match ($trxConnection) {
            'trx1' => 'log1',
            'trx2' => 'log2',
            'trx' => 'log', // 開発環境用（シャーディング前）
            default => throw new \InvalidArgumentException("Unknown trx connection: {$trxConnection}")
        };
    }
    
    /**
     * LogDB接続名から対応するTrxDB接続名を取得
     * 
     * @param string $logConnection
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function getTrxConnection(string $logConnection): string
    {
        return match ($logConnection) {
            'log1' => 'trx1',
            'log2' => 'trx2',
            'log' => 'trx', // 開発環境用（シャーディング前）
            default => throw new \InvalidArgumentException("Unknown log connection: {$logConnection}")
        };
    }
    
    /**
     * すべてのLogDB接続名を取得
     * 
     * @return array<string>
     */
    public static function getAllLogConnections(): array
    {
        return ['log1', 'log2'];
    }
    
    /**
     * すべてのTrxDB接続名を取得
     * 
     * @return array<string>
     */
    public static function getAllTrxConnections(): array
    {
        return ['trx1', 'trx2'];
    }
    
    /**
     * 指定されたTrxDB接続が有効かチェック
     * 
     * @param string $trxConnection
     * @return bool
     */
    public static function isValidTrxConnection(string $trxConnection): bool
    {
        return in_array($trxConnection, ['trx1', 'trx2', 'trx']);
    }
    
    /**
     * 指定されたLogDB接続が有効かチェック
     * 
     * @param string $logConnection
     * @return bool
     */
    public static function isValidLogConnection(string $logConnection): bool
    {
        return in_array($logConnection, ['log1', 'log2', 'log']);
    }
}
