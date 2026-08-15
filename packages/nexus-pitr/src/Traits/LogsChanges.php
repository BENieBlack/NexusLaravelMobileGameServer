<?php

namespace NexusPitr\Traits;

use DateTime;
use NexusPitr\DataTransferObjects\ChangeLog;

/**
 * LogsChanges Trait
 * 
 * Repository層にPITRログ記録機能を追加
 * _BaseTrxRepositoryで使用される
 */
trait LogsChanges
{
    /**
     * PITRログのキュー
     * 
     * @var array<ChangeLog>
     */
    private array $pitrLogQueue = [];
    
    /**
     * PITRログをキューに追加（INSERT用）
     * 
     * @param int $sysPlayerId
     * @param array $afterData
     * @param array $primaryKey
     * @return void
     */
    protected function queueInsertLog(int $sysPlayerId, array $afterData, array $primaryKey): void
    {
        $this->pitrLogQueue[] = new ChangeLog(
            uniqueRequestId: $this->resolveRequestId(),
            sysPlayerId: $sysPlayerId,
            shardConnection: $this->connection,
            tableName: $this->getTableName(),
            operation: 'INSERT',
            beforeData: null,
            afterData: $afterData,
            primaryKey: $primaryKey,
            systemAt: new DateTime(),
            apiEndpoint: $this->resolveApiEndpoint(),
            stackTrace: null
        );
    }
    
    /**
     * PITRログをキューに追加（UPDATE用）
     * 
     * @param int $sysPlayerId
     * @param array $beforeData
     * @param array $afterData 差分のみ
     * @param array $primaryKey
     * @return void
     */
    protected function queueUpdateLog(int $sysPlayerId, array $beforeData, array $afterData, array $primaryKey): void
    {
        $this->pitrLogQueue[] = new ChangeLog(
            uniqueRequestId: $this->resolveRequestId(),
            sysPlayerId: $sysPlayerId,
            shardConnection: $this->connection,
            tableName: $this->getTableName(),
            operation: 'UPDATE',
            beforeData: $beforeData,
            afterData: $afterData, // 差分のみ
            primaryKey: $primaryKey,
            systemAt: new DateTime(),
            apiEndpoint: $this->resolveApiEndpoint(),
            stackTrace: null
        );
    }
    
    /**
     * PITRログをキューに追加（DELETE用）
     * 
     * @param int $sysPlayerId
     * @param array $beforeData
     * @param array $primaryKey
     * @return void
     */
    protected function queueDeleteLog(int $sysPlayerId, array $beforeData, array $primaryKey): void
    {
        $this->pitrLogQueue[] = new ChangeLog(
            uniqueRequestId: $this->resolveRequestId(),
            sysPlayerId: $sysPlayerId,
            shardConnection: $this->connection,
            tableName: $this->getTableName(),
            operation: 'DELETE',
            beforeData: $beforeData,
            afterData: null,
            primaryKey: $primaryKey,
            systemAt: new DateTime(),
            apiEndpoint: $this->resolveApiEndpoint(),
            stackTrace: null
        );
    }
    
    /**
     * PITRログキューを取得
     * 
     * @return array<ChangeLog>
     */
    public function getPitrLogQueue(): array
    {
        return $this->pitrLogQueue;
    }
    
    /**
     * PITRログキューをクリア
     * 
     * @return void
     */
    public function clearPitrLogQueue(): void
    {
        $this->pitrLogQueue = [];
    }
    
    /**
     * リクエストIDを取得
     * 
     * @return string
     */
    private function resolveRequestId(): string
    {
        if (function_exists('request')) {
            return request()->header('X-Request-ID') 
                ?? request()->header('X-Amzn-Trace-Id') 
                ?? \Illuminate\Support\Str::uuid()->toString();
        }
        
        return \Illuminate\Support\Str::uuid()->toString();
    }
    
    /**
     * APIエンドポイントを取得
     * 
     * @return string|null
     */
    private function resolveApiEndpoint(): ?string
    {
        if (function_exists('request')) {
            return request()->path() ?? 'console';
        }
        
        return 'console';
    }
    
    /**
     * テーブル名を取得（抽象メソッド）
     * _BaseTrxRepositoryで実装される
     * 
     * @return string
     */
    abstract protected function getTableName(): string;
}
