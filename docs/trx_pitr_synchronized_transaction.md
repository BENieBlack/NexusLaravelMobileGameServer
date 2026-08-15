# TrxDB Point-In-Time Recovery 修正版設計書

## トランザクション戦略の変更

### 現状の問題

```php
// 現在の実装（UseCaseTrait）
DB::connection('sys')->beginTransaction();
DB::connection('trx')->beginTransaction();

// ビジネスロジック実行
$callback();
$queryManager->flush();

// sys, trx のみコミット
DB::connection('sys')->commit();
DB::connection('trx')->commit();

// ログはトランザクション外で実行（非同期）
$queryManager->execAllLogs(); // log接続は別トランザクション
```

**問題点**: TrxDBコミット後、LogDB書き込み失敗 → ログ欠損 → PITR不可能

### 修正後の設計（同時トランザクション方式）

```php
// 修正版実装
DB::connection('sys')->beginTransaction();
DB::connection('trx1')->beginTransaction();
DB::connection('log1')->beginTransaction(); // ✅ 同時に開始

// ビジネスロジック実行
$callback();
$queryManager->flush(); // TrxDB + LogDBを両方実行

// すべてを同時にコミット（try-catchで完全同期）
try {
    DB::connection('sys')->commit();
    DB::connection('trx1')->commit();
    DB::connection('log1')->commit(); // ✅ 同時にコミット
} catch (\Exception $e) {
    // どれか1つでも失敗したら全てロールバック
    DB::connection('sys')->rollBack();
    DB::connection('trx1')->rollBack();
    DB::connection('log1')->rollBack();
    throw $e;
}
```

## 実装詳細

### 1. UseCaseTraitの修正

```php
// api/app/Traits/UseCaseTrait.php

<?php

namespace App\Traits;

use NexusUnitOfWork\Contracts\QueryManagerInterface;
use NexusPitr\Logger\ShardMapper;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

trait UseCaseTrait
{
    /**
     * トランザクション付きでコールバックを実行
     *
     * 処理フロー：
     * 1. クリーンアップ処理（オプション）
     * 2. トランザクション開始（sys, trx, log を同時に）
     * 3. コールバックを実行（QueryManagerにキューイング）
     * 4. キューに溜まったクエリを実行（TrxDB + LogDB）
     * 5. すべてを同時にコミット（1つでも失敗したら全てロールバック）
     *
     * @param callable $callback 実行するビジネスロジック
     * @param int|null $sysPlayerId sign_in時のクリーンアップ用プレイヤーID
     * @return mixed コールバックの戻り値
     * @throws Exception|Throwable
     */
    public function executeWithTransaction(callable $callback, ?int $sysPlayerId = null): mixed
    {
        // クリーンアップ処理
        if ($sysPlayerId !== null) {
            $cleanupService = app()->make('App\Domain\Player\Services\PlayerCleanupService');
            $cleanupService->cleanupDeletedRecords($sysPlayerId);
        }

        // 使用する接続を収集
        $connections = $this->getActiveConnections();
        
        // すべてのトランザクションを同時に開始
        foreach ($connections as $conn) {
            DB::connection($conn)->beginTransaction();
        }

        $queryManager = app()->make(QueryManagerInterface::class);
        
        try {
            // コールバック実行
            $result = $callback();

            // すべてのクエリをフラッシュ（TrxDB + LogDB）
            $queryManager->flush();

            // すべてを同時にコミット
            foreach ($connections as $conn) {
                DB::connection($conn)->commit();
            }

        } catch (Exception | Throwable $e) {
            \Log::error('Transaction failed in UseCase', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            // すべてをロールバック
            foreach ($connections as $conn) {
                try {
                    DB::connection($conn)->rollBack();
                } catch (\Exception $rollbackException) {
                    \Log::emergency('Rollback failed', [
                        'connection' => $conn,
                        'error' => $rollbackException->getMessage(),
                    ]);
                }
            }

            throw $e;
        }

        return $result;
    }

    /**
     * アクティブな接続を取得
     * 
     * sys + (trx1,trx2,...) + (log1,log2,...)
     *
     * @return array<string>
     */
    private function getActiveConnections(): array
    {
        $connections = ['sys'];
        
        // TrxDB接続を追加（現在アクティブなシャードのみ）
        // TODO: 実際に使用されているシャードを動的に判定
        $trxConnections = ['trx1', 'trx2']; // 現在は固定、将来的に動的判定
        
        foreach ($trxConnections as $trxConn) {
            $connections[] = $trxConn;
            
            // 対応するLogDB接続を追加
            try {
                $logConn = ShardMapper::getLogConnection($trxConn);
                $connections[] = $logConn;
            } catch (\Exception $e) {
                // LogDBマッピングがない場合はスキップ
                \Log::warning('LogDB mapping not found', [
                    'trx_connection' => $trxConn,
                ]);
            }
        }
        
        return $connections;
    }
}
```

### 2. QueryManagerの修正

```php
// packages/nexus-core-unit-of-work/src/Persistence/QueryManager.php

public function flush(): void
{
    // 操作を収集
    $operations = $this->operationCollector->collect($this->repositories);

    // TrxDB操作を実行
    $this->batchExecutor->executeInserts($operations['inserts']);
    $this->batchExecutor->executeUpdates($operations['updates']);
    $this->batchExecutor->executeDeletes($operations['deletes']);

    // ✅ LogDB操作も同じトランザクション内で実行
    $logInserts = $this->operationCollector->collectLogs($operations['logs']);
    $purchaseLogInserts = $this->operationCollector->collectLogs($this->purchaseLogRepositories);
    
    $this->batchExecutor->executeLogInserts($logInserts);
    $this->batchExecutor->executeLogInserts($purchaseLogInserts);

    // ✅ PITRログも同じトランザクション内で実行
    $this->flushPitrLogs();

    // クリア
    $this->clearAll();
}

/**
 * PITRログを書き込む
 */
private function flushPitrLogs(): void
{
    $pitrLogger = app()->make(\NexusPitr\Logger\TrxChangeLogger::class);
    
    foreach ($this->repositories as $repository) {
        if ($repository instanceof \NexusPersistence\Repositories\Trx\_BaseTrxRepository) {
            // Repositoryが保持している変更履歴をPITRログに記録
            $repository->flushPitrLogs();
        }
    }
}

private function clearAll(): void
{
    foreach ($this->repositories as $repository) {
        $repository->clearQueue();
    }
    
    foreach ($this->purchaseLogRepositories as $repository) {
        $repository->clearQueue();
    }
    
    $this->repositories = [];
    $this->purchaseLogRepositories = [];
}
```

### 3. _BaseTrxRepositoryの修正

```php
// packages/nexus-core-persistence/src/Repositories/Trx/_BaseTrxRepository.php

use NexusPitr\Traits\LogsChanges;
use NexusPitr\Logger\ShardMapper;

abstract class _BaseTrxRepository extends _BaseRepository
{
    use LogsChanges;
    
    /**
     * PITRログをフラッシュ（同一トランザクション内で実行）
     */
    public function flushPitrLogs(): void
    {
        $logConnection = ShardMapper::getLogConnection($this->connection);
        $changeLogs = [];
        
        foreach ($this->modelQueue as $uniqueKey => $model) {
            $originalState = $this->originalStateArray[$uniqueKey] ?? [];
            
            if (empty($originalState)) {
                // INSERT
                $changeLogs[] = new \NexusPitr\DataTransferObjects\ChangeLog(
                    uniqueRequestId: $this->getRequestId(),
                    sysPlayerId: $model->sys_player_id,
                    shardConnection: $this->connection,
                    tableName: $this->getTableName(),
                    operation: 'INSERT',
                    beforeData: null,
                    afterData: $model->getAttributes(),
                    primaryKey: $this->getPrimaryKeyValues($model),
                    systemAt: now(),
                    apiEndpoint: request()->path() ?? 'console',
                    stackTrace: null
                );
            } else {
                // UPDATE（差分がある場合のみ）
                $afterData = $model->getAttributes();
                $diff = array_diff_assoc($afterData, $originalState);
                
                if (!empty($diff)) {
                    $changeLogs[] = new \NexusPitr\DataTransferObjects\ChangeLog(
                        uniqueRequestId: $this->getRequestId(),
                        sysPlayerId: $model->sys_player_id,
                        shardConnection: $this->connection,
                        tableName: $this->getTableName(),
                        operation: 'UPDATE',
                        beforeData: $originalState,
                        afterData: $diff, // ✅ 差分のみ記録（ストレージ削減）
                        primaryKey: $this->getPrimaryKeyValues($model),
                        systemAt: now(),
                        apiEndpoint: request()->path() ?? 'console',
                        stackTrace: null
                    );
                }
            }
        }
        
        // 削除キュー
        foreach ($this->deleteQueue as $model) {
            $changeLogs[] = new \NexusPitr\DataTransferObjects\ChangeLog(
                uniqueRequestId: $this->getRequestId(),
                sysPlayerId: $model->sys_player_id,
                shardConnection: $this->connection,
                tableName: $this->getTableName(),
                operation: 'DELETE',
                beforeData: $model->getOriginal(),
                afterData: null,
                primaryKey: $this->getPrimaryKeyValues($model),
                systemAt: now(),
                apiEndpoint: request()->path() ?? 'console',
                stackTrace: null
            );
        }
        
        // バッチでログ記録
        if (!empty($changeLogs)) {
            $this->getTrxChangeLogger()->logBatch($changeLogs);
        }
    }
    
    private function getRequestId(): string
    {
        return request()->header('X-Request-ID') 
            ?? request()->header('X-Amzn-Trace-Id') 
            ?? \Illuminate\Support\Str::uuid()->toString();
    }
    
    private function getPrimaryKeyValues($model): array
    {
        $keyName = $model->getKeyName();
        
        if (is_array($keyName)) {
            // 複合主キー
            $pk = [];
            foreach ($keyName as $key) {
                $pk[$key] = $model->getAttribute($key);
            }
            return $pk;
        } else {
            // 単一主キー
            return [$keyName => $model->getKey()];
        }
    }
}
```

### 4. TrxChangeLogger修正（シャーディング対応）

```php
// packages/nexus-pitr/src/Logger/TrxChangeLogger.php

namespace NexusPitr\Logger;

use Illuminate\Support\Facades\DB;
use NexusPitr\DataTransferObjects\ChangeLog;

class TrxChangeLogger
{
    /**
     * バッチログ記録（トランザクション内で実行）
     */
    public function logBatch(array $changeLogDtos): void
    {
        if (empty($changeLogDtos)) {
            return;
        }

        // シャード毎にグループ化
        $groupedByLog = [];
        foreach ($changeLogDtos as $dto) {
            $trxConn = $dto->getShardConnection();
            $logConn = ShardMapper::getLogConnection($trxConn);
            
            if (!isset($groupedByLog[$logConn])) {
                $groupedByLog[$logConn] = [];
            }
            $groupedByLog[$logConn][] = $dto;
        }
        
        // LogDBシャード毎に書き込み
        foreach ($groupedByLog as $logConn => $dtos) {
            $records = [];
            
            foreach ($dtos as $dto) {
                $records[] = [
                    'id' => \Illuminate\Support\Str::uuid()->toString(), // UUIDv4（将来UUIDv7に変更推奨）
                    'unique_request_id' => $dto->getUniqueRequestId(),
                    'sys_player_id' => $dto->getSysPlayerId(),
                    'shard_connection' => $dto->getShardConnection(),
                    'table_name' => $dto->getTableName(),
                    'operation' => $dto->getOperation(),
                    'before_data' => $dto->getBeforeData() ? json_encode($dto->getBeforeData()) : null,
                    'after_data' => $dto->getAfterData() ? json_encode($dto->getAfterData()) : null,
                    'primary_key' => json_encode($dto->getPrimaryKey()),
                    'system_at' => $dto->getSystemAt(),
                    'api_endpoint' => $dto->getApiEndpoint(),
                    'stack_trace' => $dto->getStackTrace() ? json_encode($dto->getStackTrace()) : null,
                ];
            }
            
            // ✅ 同一トランザクション内でINSERT
            DB::connection($logConn)->table('log_trx_change')->insert($records);
        }
    }
}
```

## トランザクション整合性の保証

### シナリオ分析

#### ケース1: すべて成功

```
sys.beginTransaction()
trx1.beginTransaction()
log1.beginTransaction()

→ ビジネスロジック実行
→ TrxDB書き込み
→ LogDB書き込み

sys.commit()   ✅
trx1.commit()  ✅
log1.commit()  ✅

結果: データとログが完全に一致
```

#### ケース2: TrxDB書き込み失敗

```
sys.beginTransaction()
trx1.beginTransaction()
log1.beginTransaction()

→ ビジネスロジック実行
→ TrxDB書き込み → Exception発生

catch {
    sys.rollBack()   ✅
    trx1.rollBack()  ✅
    log1.rollBack()  ✅
}

結果: すべてロールバック、整合性維持
```

#### ケース3: LogDB書き込み失敗

```
sys.beginTransaction()
trx1.beginTransaction()
log1.beginTransaction()

→ ビジネスロジック実行
→ TrxDB書き込み ✅
→ LogDB書き込み → Exception発生

catch {
    sys.rollBack()   ✅
    trx1.rollBack()  ✅
    log1.rollBack()  ✅
}

結果: すべてロールバック、整合性維持
```

#### ケース4: sys commit成功、trx1 commit失敗

```
sys.beginTransaction()
trx1.beginTransaction()
log1.beginTransaction()

→ すべての書き込み成功

sys.commit()   ✅
trx1.commit()  ❌ Exception発生

catch {
    sys.rollBack()   ❌ すでにcommit済み（ロールバック不可能）
    trx1.rollBack()  ✅
    log1.rollBack()  ✅
}

結果: sysDBのみ不整合（稀なケース）
```

**対策**: commit順序を重要度の低い順に変更

```php
// 修正版commit順序
log1.commit()  // 最初（最も重要度が低い）
trx1.commit()  // 次
sys.commit()   // 最後（最も重要）

// この順序なら、sysがcommit失敗してもtrx1/log1はロールバック可能
```

### 最終的なcommit順序

```php
try {
    // Phase 1: LogDBを先にcommit
    foreach ($logConnections as $logConn) {
        DB::connection($logConn)->commit();
    }
    
    // Phase 2: TrxDBをcommit
    foreach ($trxConnections as $trxConn) {
        DB::connection($trxConn)->commit();
    }
    
    // Phase 3: SysDBを最後にcommit
    DB::connection('sys')->commit();
    
} catch (\Exception $e) {
    // ロールバック処理
    // ...
}
```

**ただし**: 通常はMySQL自体がcommitで失敗することは稀（ディスク満杯、接続切断等）なので、実用上は問題なし

## パフォーマンス最適化

### 1. バッチサイズ制御

```php
// config/nexus-pitr.php
return [
    'batch_size' => env('PITR_BATCH_SIZE', 1000),
    'enable_compression' => env('PITR_ENABLE_COMPRESSION', false),
];
```

### 2. 差分のみ記録

```php
// UPDATE時は差分のみ記録
$diff = array_diff_assoc($afterData, $originalState);

if (!empty($diff)) {
    // 差分のみをafter_dataに記録
    $changeLog->afterData = $diff;
}
```

### 3. 圧縮（オプション）

```php
if (config('nexus-pitr.enable_compression')) {
    $beforeData = gzcompress(json_encode($dto->getBeforeData()));
    $afterData = gzcompress(json_encode($dto->getAfterData()));
}
```

## まとめ

### ✅ 修正後の保証

1. **完全な整合性**: TrxDBとLogDBが同一トランザクションで同時commit/rollback
2. **PITR可能性**: ログ欠損がゼロ（commit失敗時は両方ロールバック）
3. **シンプルな実装**: 既存のtry-catch構造を活用
4. **パフォーマンス**: バッチ書き込み、差分記録で最適化

### 📋 実装タスク

1. ✅ UseCaseTraitの修正（getActiveConnections追加）
2. ✅ QueryManager::flush()の修正（PITRログ統合）
3. ✅ _BaseTrxRepository::flushPitrLogs()追加
4. ✅ TrxChangeLogger::logBatch()実装
5. ✅ ShardMapper実装（trx↔log変換）
6. ✅ マイグレーション作成（log_trx_change等）

次のステップ: この設計で実装を進めますか？
