<?php

namespace LaravelUnitOfWork\Persistence;

use LaravelUnitOfWork\Contracts\QueryManagerInterface;
use LaravelPersistence\Repositories\_BaseRepository;
use LaravelPersistence\Repositories\Log\_BaseLogRepository;
use LaravelPersistence\Repositories\Sys\_BaseSysRepository;
use LaravelUnitOfWork\Persistence\QueryManager\OperationCollector;
use LaravelUnitOfWork\Persistence\QueryManager\BatchExecutor;

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
     * @var array<_BaseRepository>
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
     * @param _BaseRepository $repository
     * @param bool $isPurchaseLog 課金関連のログかどうか（LogRepositoryの場合のみ使用）
     * @return void
     */
    public function registerRepository(_BaseRepository $repository, bool $isPurchaseLog = false): void
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
     * 溜め込んだ全てのモデルを実行する
     * 各Repositoryからモデルを取り出し、実行時にINSERT/UPDATEを判定
     *
     * @return void
     * @throws \Exception
     */
    public function execAllQuery(): void
    {
        // 操作を収集
        $operations = $this->operationCollector->collect($this->repositories);

        // 各操作を実行
        $this->batchExecutor->executeInserts($operations['inserts']);
        $this->batchExecutor->executeUpdates($operations['updates']);
        $this->batchExecutor->executeDeletes($operations['deletes']);

        // ログのINSERT処理
        $logInserts = $this->operationCollector->collectLogs($operations['logs']);
        $this->batchExecutor->executeLogInserts($logInserts);

        // クリア
        $this->clear();
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
