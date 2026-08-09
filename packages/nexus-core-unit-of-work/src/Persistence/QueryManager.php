<?php

namespace NexusUnitOfWork\Persistence;

use NexusUnitOfWork\Contracts\QueryManagerInterface;
use NexusPersistence\Repositories\_BaseRepositoryInterface;
use NexusPersistence\Repositories\Log\_BaseLogRepository;
use NexusPersistence\Repositories\Sys\_BaseSysRepository;
use NexusPersistence\Repositories\Trx\_BaseTrxRepository;
use NexusUnitOfWork\Persistence\QueryManager\OperationCollector;
use NexusUnitOfWork\Persistence\QueryManager\BatchExecutor;
use NexusPitr\Logger\TrxChangeLogger;

/**
 * QueryManager
 *
 * 全データベース（trx, sys, log）への変更を溜め込み、一括で実行する
 * Unit of Work パターンの実装
 *
 * - Trx: バッチINSERT、INSERT/UPDATE/DELETE対応
 * - Sys: sys_playerのみ個別INSERT（IDを取得）、その他はバッチINSERT、INSERT/UPDATE/DELETE対応
 * - Log: バッチINSERT、INSERTのみ、課金ログと通常ログを分離
 */
class QueryManager implements QueryManagerInterface
{
    /**
     * 登録されたRepositoryのリスト
     *
     * @var array<_BaseRepositoryInterface>
     */
    private array $repositories = [];

    /**
     * 課金関連のログRepositoryのリスト
     *
     * @var array<_BaseLogRepository>
     */
    private array $purchaseLogRepositories = [];

    private OperationCollector $operationCollector;
    private BatchExecutor $batchExecutor;

    public function __construct()
    {
        $this->operationCollector = new OperationCollector();
        $this->batchExecutor = new BatchExecutor();
    }

    /**
     * Repositoryを登録する
     *
     * @param _BaseRepositoryInterface $repository
     * @param bool $isPurchaseLog 課金関連のログかどうか（LogRepositoryの場合のみ使用）
     * @return void
     */
    public function registerRepository(_BaseRepositoryInterface $repository, bool $isPurchaseLog = false): void
    {
        // 重複登録を防ぐ（同じインスタンスは1回のみ登録）
        $hash = spl_object_hash($repository);

        // LogRepositoryで課金ログの場合は別リストに登録
        if ($repository instanceof _BaseLogRepository && $isPurchaseLog) {
            if (!isset($this->purchaseLogRepositories[$hash])) {
                $this->purchaseLogRepositories[$hash] = $repository;
            }
        } else {
            if (!isset($this->repositories[$hash])) {
                $this->repositories[$hash] = $repository;
            }
        }
    }

    /**
     * 課金関連のログのみを実行する
     * トランザクション内で呼び出される
     *
     * @return void
     * @throws \Exception
     */
    public function execPurchaseQuery(): void
    {
        // 課金ログのバッチINSERTを実行
        $logInserts = $this->operationCollector->collectLogs($this->purchaseLogRepositories);
        $this->batchExecutor->executeLogInserts($logInserts);

        // 課金ログRepositoryのキューをクリア
        foreach ($this->purchaseLogRepositories as $repository) {
            $repository->clearQueue();
        }

        $this->purchaseLogRepositories = [];
    }

    /**
     * 溜め込んだ全てのモデルを実行する（ログを除く）
     * 各Repositoryからモデルを取り出し、実行時にINSERT/UPDATEを判定
     *
     * @return void
     * @throws \Exception
     */
    public function execAllQuery(): void
    {
        // 操作を収集
        $operations = $this->operationCollector->collect($this->repositories);

        // 各操作を実行（ログ以外）
        $this->batchExecutor->executeInserts($operations['inserts']);
        $this->batchExecutor->executeUpdates($operations['updates']);
        $this->batchExecutor->executeDeletes($operations['deletes']);

        // PITRログをLogDBに記録（同一トランザクション内で実行）
        $this->flushPitrLogs();

        // ログはトランザクション外で実行するため、ここでは実行しない
        // $operations['logs'] は execAllLogs() で処理される

        // クリア（ログリポジトリは残す）
        $this->clearExceptLogs();
    }

    /**
     * PITRログをLogDBに記録（同一トランザクション内で実行）
     * 
     * @return void
     */
    private function flushPitrLogs(): void
    {
        try {
            $trxChangeLogger = app()->make(TrxChangeLogger::class);
            $allPitrLogs = [];
            
            // 全TrxRepositoryからPITRログを収集
            foreach ($this->repositories as $repository) {
                if ($repository instanceof _BaseTrxRepository) {
                    $pitrLogs = $repository->getPitrLogQueue();
                    if (!empty($pitrLogs)) {
                        $allPitrLogs = array_merge($allPitrLogs, $pitrLogs);
                    }
                }
            }
            
            // LogDBにバッチ記録（同一トランザクション内）
            if (!empty($allPitrLogs)) {
                $trxChangeLogger->logBatch($allPitrLogs);
            }
            
            // PITRログキューをクリア
            foreach ($this->repositories as $repository) {
                if ($repository instanceof _BaseTrxRepository) {
                    $repository->clearPitrLogQueue();
                }
            }
            
        } catch (\Exception $e) {
            // PITRログ記録失敗は致命的エラー（トランザクション全体を失敗させる）
            \Log::error('Failed to write PITR logs (critical)', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e; // トランザクションをロールバックさせる
        }
    }

    /**
     * ログのみを実行する（トランザクション外で呼び出される）
     *
     * @return void
     */
    public function execAllLogs(): void
    {
        try {
            // 通常ログRepositoryから収集
            $operations = $this->operationCollector->collect($this->repositories);
            $logInserts = $this->operationCollector->collectLogs($operations['logs']);
            
            // 課金ログRepositoryから収集
            $purchaseLogInserts = $this->operationCollector->collectLogs($this->purchaseLogRepositories);
            
            // 両方を実行
            $this->batchExecutor->executeLogInserts($logInserts);
            $this->batchExecutor->executeLogInserts($purchaseLogInserts);
            
            // ログRepositoryをクリア
            foreach ($operations['logs'] as $repository) {
                $repository->clearQueue();
            }
            foreach ($this->purchaseLogRepositories as $repository) {
                $repository->clearQueue();
            }
            
            // 課金ログリポジトリリストをクリア
            $this->purchaseLogRepositories = [];
            
        } catch (\Exception | \Throwable $e) {
            // ログ書き込み失敗はビジネストランザクションに影響させない
            \Log::error('Failed to write logs (non-critical)', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * ログRepository以外をクリア
     *
     * @return void
     */
    private function clearExceptLogs(): void
    {
        // 各Repositoryのキューをクリア（ログ以外）
        foreach ($this->repositories as $repository) {
            if (!($repository instanceof _BaseLogRepository)) {
                $repository->clearQueue();
            }
        }

        // リポジトリリストからログ以外を削除
        $this->repositories = array_filter(
            $this->repositories,
            fn($repository) => $repository instanceof _BaseLogRepository
        );
    }

    /**
     * 登録されたすべてのリポジトリの操作をバッチ実行
     * execAllQuery()のエイリアス
     *
     * @return void
     * @throws \Exception
     */
    public function flush(): void
    {
        $this->execAllQuery();
    }

    /**
     * 全てのRepositoryとモデルをクリア
     *
     * @return void
     */
    public function clear(): void
    {
        // 各Repositoryのキューをクリア
        foreach ($this->repositories as $repository) {
            $repository->clearQueue();
        }

        foreach ($this->purchaseLogRepositories as $repository) {
            $repository->clearQueue();
        }

        $this->repositories = [];
        $this->purchaseLogRepositories = [];
    }
}
