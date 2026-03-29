<?php

namespace App\Repositories\Log;

use Illuminate\Support\Facades\DB;

/**
 * QueryLogManager
 * 
 * ログデータベース（log）への変更を溜め込み、一括で実行する
 * Unit of Work パターンの実装
 * 
 * 課金関連のログは必ずトランザクション内で実行される
 * その他のログは設定に応じてトランザクション内/外を選択可能
 */
class QueryLogManager
{
    /**
     * 課金関連のログRepositoryのリスト
     * 
     * @var array<_BaseLogRepository>
     */
    private array $purchaseRepositories = [];

    /**
     * 通常のログRepositoryのリスト
     * 
     * @var array<_BaseLogRepository>
     */
    private array $normalRepositories = [];

    /**
     * Repositoryを登録する
     *
     * @param _BaseLogRepository $repository
     * @param bool $isPurchase 課金関連のログかどうか
     * @return void
     */
    public function registerRepository(_BaseLogRepository $repository, bool $isPurchase = false): void
    {
        // 重複登録を防ぐ（同じインスタンスは1回のみ登録）
        $hash = spl_object_hash($repository);
        
        if ($isPurchase) {
            if (!isset($this->purchaseRepositories[$hash])) {
                $this->purchaseRepositories[$hash] = $repository;
            }
        } else {
            if (!isset($this->normalRepositories[$hash])) {
                $this->normalRepositories[$hash] = $repository;
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
        // テーブルごとにグループ化してバッチインサート
        $insertsByTable = $this->collectModelsFromRepositories($this->purchaseRepositories);
        
        foreach ($insertsByTable as $item) {
            if (empty($item['records'])) {
                continue;
            }
            
            DB::connection($item['connection'])
                ->table($item['table'])
                ->insert($item['records']);
        }
        
        // 課金ログRepositoryのキューをクリア
        foreach ($this->purchaseRepositories as $repository) {
            $repository->clearQueue();
        }
        
        $this->purchaseRepositories = [];
    }

    /**
     * 全てのログクエリを実行する
     * 設定に応じてトランザクション内/外で呼び出される
     *
     * @return void
     * @throws \Exception
     */
    public function execAllQuery(): void
    {
        // テーブルごとにグループ化してバッチインサート
        $insertsByTable = $this->collectModelsFromRepositories($this->normalRepositories);
        
        foreach ($insertsByTable as $item) {
            if (empty($item['records'])) {
                continue;
            }
            
            DB::connection($item['connection'])
                ->table($item['table'])
                ->insert($item['records']);
        }
        
        // 通常ログRepositoryのキューをクリア
        foreach ($this->normalRepositories as $repository) {
            $repository->clearQueue();
        }
        
        $this->normalRepositories = [];
    }

    /**
     * Repositoryからモデルを収集してテーブルごとにグループ化
     *
     * @param array $repositories
     * @return array
     */
    private function collectModelsFromRepositories(array $repositories): array
    {
        $grouped = [];
        
        foreach ($repositories as $repository) {
            $connection = $repository->getConnection();
            $table = $repository->getTableName();
            $models = $repository->getQueuedModels();
            
            $key = "{$connection}.{$table}";
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'connection' => $connection,
                    'table' => $table,
                    'records' => [],
                ];
            }
            
            foreach ($models as $model) {
                $grouped[$key]['records'][] = $model->getAttributes();
            }
        }
        
        return $grouped;
    }

    /**
     * 溜め込んだクエリを全てクリアする
     *
     * @return void
     */
    public function clear(): void
    {
        foreach ($this->purchaseRepositories as $repository) {
            $repository->clearQueue();
        }
        
        foreach ($this->normalRepositories as $repository) {
            $repository->clearQueue();
        }
        
        $this->purchaseRepositories = [];
        $this->normalRepositories = [];
    }

    /**
     * 溜め込んでいるクエリの数を取得（デバッグ用）
     *
     * @return array
     */
    public function getQueueCount(): array
    {
        $purchaseCount = 0;
        $normalCount = 0;
        
        foreach ($this->purchaseRepositories as $repository) {
            $purchaseCount += count($repository->getQueuedModels());
        }
        
        foreach ($this->normalRepositories as $repository) {
            $normalCount += count($repository->getQueuedModels());
        }
        
        return [
            'purchase_repositories' => count($this->purchaseRepositories),
            'normal_repositories' => count($this->normalRepositories),
            'purchase' => $purchaseCount,
            'normal' => $normalCount,
        ];
    }
}
