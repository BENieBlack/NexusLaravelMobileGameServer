<?php

namespace App\Repositories;

use App\Repositories\_BaseRepository;
use App\Repositories\Log\_BaseLogRepository;
use App\Repositories\Sys\_BaseSysRepository;
use App\Repositories\Trx\_BaseTrxRepository;
use Illuminate\Support\Facades\DB;

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
class QueryManager
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
        $this->execLogInserts($this->purchaseLogRepositories);
        
        // 課金ログRepositoryのキューをクリア
        foreach ($this->purchaseLogRepositories as $repository) {
            $repository->clearQueue();
        }
        
        $this->purchaseLogRepositories = [];
    }

    /**
     * Sysデータベースのみを実行する
     * PlayerServiceなどで、IDを取得するために部分的に実行する際に使用
     * トランザクション内で呼び出される
     *
     * @return void
     * @throws \Exception
     */
    public function execSysQuery(): void
    {
        // SysRepositoryのみを抽出
        $sysRepositories = [];
        foreach ($this->repositories as $hash => $repository) {
            if ($repository instanceof _BaseSysRepository) {
                $sysRepositories[$hash] = $repository;
            }
        }
        
        // SysRepositoryがない場合は何もしない
        if (empty($sysRepositories)) {
            return;
        }
        
        // モデルをテーブルごとにグループ化
        $insertsByTable = [];
        $updates = [];
        $deletes = [];
        
        foreach ($sysRepositories as $repository) {
            /** @var _BaseSysRepository $repository */
            $connection = $repository->getConnection();
            $table = $repository->getTableName();
            $models = $repository->getQueuedModels();
            $originalStates = $repository->getOriginalStates();
            
            foreach ($models as $uniqueKey => $model) {
                if ($model->exists) {
                    // 既存モデル → UPDATE
                    $dirtyAttributes = $model->getDirty();
                    
                    if (!empty($dirtyAttributes)) {
                        $updates[] = [
                            'connection' => $connection,
                            'table' => $table,
                            'model' => $model,
                            'data' => $dirtyAttributes,
                            'repository' => $repository,
                            'uniqueKey' => $uniqueKey,
                            'originalState' => $originalStates[$uniqueKey] ?? [],
                        ];
                    }
                } else {
                    // 新規モデル → INSERT
                    $key = "{$connection}.{$table}";
                    
                    if (!isset($insertsByTable[$key])) {
                        $insertsByTable[$key] = [
                            'connection' => $connection,
                            'table' => $table,
                            'records' => [],
                            'models' => [],
                            'repository' => $repository,
                            'originalStates' => [],
                        ];
                    }
                    
                    $insertsByTable[$key]['records'][] = $model->getAttributes();
                    $insertsByTable[$key]['models'][] = $model;
                    $insertsByTable[$key]['originalStates'][] = $originalStates[$uniqueKey] ?? [];
                }
            }
            
            // 削除対象のモデルを取り出す
            $deleteModels = $repository->getQueuedDeleteModels();
            foreach ($deleteModels as $model) {
                $deletes[] = [
                    'connection' => $connection,
                    'table' => $table,
                    'model' => $model,
                ];
            }
        }
        
        // INSERT処理（sys_player/sys_player_deviceのみ個別INSERT、その他はバッチINSERT）
        foreach ($insertsByTable as $item) {
            $connection = $item['connection'];
            $table = $item['table'];
            $records = $item['records'];
            $models = $item['models'];
            $repository = $item['repository'];
            $originalStates = $item['originalStates'];
            
            // sys_playerテーブルのみ個別INSERT（IDを取得）
            if ($table === 'sys_player') {
                foreach ($records as $index => $record) {
                    $id = DB::connection($connection)
                        ->table($table)
                        ->insertGetId($record);
                    
                    // モデルにIDを設定
                    $models[$index]->setAttribute('id', $id);
                    $models[$index]->exists = true;
                    
                    // afterSaveフックを呼び出す
                    $originalState = $originalStates[$index] ?? [];
                    $repository->afterSave($models[$index], $originalState);
                }
            } else {
                // その他のテーブルはバッチINSERT
                DB::connection($connection)
                    ->table($table)
                    ->insert($records);
                
                // 全てのモデルにexists = trueを設定
                foreach ($models as $index => $model) {
                    $model->exists = true;
                    
                    // afterSaveフックを呼び出す
                    $originalState = $originalStates[$index] ?? [];
                    $repository->afterSave($model, $originalState);
                }
            }
        }
        
        // UPDATE処理
        foreach ($updates as $item) {
            /** @var _BaseSysRepository $repository */
            $repository = $item['repository'];
            $model = $item['model'];
            $where = $this->buildWhereCondition($model);
            
            DB::connection($item['connection'])
                ->table($item['table'])
                ->where($where)
                ->update($item['data']);
            
            // afterSaveフックを呼び出す（UPDATE）
            $repository->afterSave($model, $item['originalState']);
        }

        // DELETE処理
        foreach ($deletes as $item) {
            $model = $item['model'];
            $where = $this->buildWhereCondition($model);
            
            DB::connection($item['connection'])
                ->table($item['table'])
                ->where($where)
                ->delete();
        }
        
        // 処理済みSysRepositoryのキューをクリア
        foreach ($sysRepositories as $repository) {
            $repository->clearQueue();
        }
        
        // 処理済みSysRepositoryを登録リストから削除
        foreach ($sysRepositories as $hash => $repository) {
            unset($this->repositories[$hash]);
        }
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
        // モデルをテーブルごとにグループ化
        $insertsByTable = [];
        $updates = [];
        $deletes = [];
        $logRepositories = [];
        
        // 各Repositoryからモデルを取り出す
        foreach ($this->repositories as $repository) {
            // LogRepositoryは別処理
            if ($repository instanceof _BaseLogRepository) {
                $logRepositories[] = $repository;
                continue;
            }
            
            // LogRepository以外（TrxRepositoryまたはSysRepository）
            /** @var _BaseTrxRepository|_BaseSysRepository $repository */
            $connection = $repository->getConnection();
            $table = $repository->getTableName();
            $models = $repository->getQueuedModels();
            $originalStates = $repository->getOriginalStates();
            
            foreach ($models as $uniqueKey => $model) {
                if ($model->exists) {
                    // 既存モデル → UPDATE
                    $dirtyAttributes = $model->getDirty();
                    
                    if (!empty($dirtyAttributes)) {
                        $updates[] = [
                            'connection' => $connection,
                            'table' => $table,
                            'model' => $model,
                            'data' => $dirtyAttributes,
                            'repository' => $repository,
                            'uniqueKey' => $uniqueKey,
                            'originalState' => $originalStates[$uniqueKey] ?? [],
                        ];
                    }
                } else {
                    // 新規モデル → INSERT
                    $key = "{$connection}.{$table}";
                    
                    if (!isset($insertsByTable[$key])) {
                        $insertsByTable[$key] = [
                            'connection' => $connection,
                            'table' => $table,
                            'records' => [],
                            'models' => [],
                            'repository' => $repository,
                            'originalStates' => [],
                        ];
                    }
                    
                    $insertsByTable[$key]['records'][] = $model->getAttributes();
                    $insertsByTable[$key]['models'][] = $model;
                    $insertsByTable[$key]['originalStates'][] = $originalStates[$uniqueKey] ?? [];
                }
            }

            // 削除対象のモデルを取り出す（LogRepository以外）
            if (!$repository instanceof _BaseLogRepository) {
                $deleteModels = $repository->getQueuedDeleteModels();
                foreach ($deleteModels as $model) {
                    $deletes[] = [
                        'connection' => $connection,
                        'table' => $table,
                        'model' => $model,
                    ];
                }
            }
        }
        
        // INSERT処理
        foreach ($insertsByTable as $item) {
            if (empty($item['records'])) {
                continue;
            }
            
            $models = $item['models'];
            $records = $item['records'];
            $originalStates = $item['originalStates'];
            /** @var _BaseTrxRepository|_BaseSysRepository $repository */
            $repository = $item['repository'];
            $table = $item['table'];
            
            // sys_playerテーブルのみ個別INSERT（IDを取得）
            if ($table === 'sys_player') {
                foreach ($models as $index => $model) {
                    $attributes = $records[$index];
                    
                    // INSERTを実行してIDを取得
                    $id = DB::connection($item['connection'])
                        ->table($table)
                        ->insertGetId($attributes);
                    
                    // モデルにIDをセット
                    $model->setAttribute($model->getKeyName(), $id);
                    $model->exists = true;
                    
                    // afterSaveフックを呼び出す（INSERT）
                    $originalState = $originalStates[$index] ?? [];
                    $repository->afterSave($model, $originalState);
                }
            } else {
                // その他のテーブルはバッチINSERT
                DB::connection($item['connection'])
                    ->table($table)
                    ->insert($records);
                
                // バッチINSERT後に、LAST_INSERT_ID()で最初のレコードのIDを取得
                $firstId = DB::connection($item['connection'])->getPdo()->lastInsertId();
                
                // モデルにexists = trueとIDを設定し、afterSaveフックを呼び出す（INSERT）
                foreach ($models as $index => $model) {
                    $model->exists = true;
                    
                    // auto-incrementのIDを設定（複数レコードの場合は連番）
                    if ($firstId && $model->getIncrementing()) {
                        $model->setAttribute($model->getKeyName(), $firstId + $index);
                    }
                    
                    $originalState = $originalStates[$index] ?? [];
                    $repository->afterSave($model, $originalState);
                }
            }
        }
        
        // UPDATE処理
        foreach ($updates as $item) {
            /** @var _BaseTrxRepository|_BaseSysRepository $repository */
            $repository = $item['repository'];
            $model = $item['model'];
            $where = $this->buildWhereCondition($model);
            
            DB::connection($item['connection'])
                ->table($item['table'])
                ->where($where)
                ->update($item['data']);
            
            // afterSaveフックを呼び出す（UPDATE）
            $repository->afterSave($model, $item['originalState']);
        }

        // DELETE処理
        foreach ($deletes as $item) {
            $model = $item['model'];
            $where = $this->buildWhereCondition($model);
            
            DB::connection($item['connection'])
                ->table($item['table'])
                ->where($where)
                ->delete();
        }
        
        // ログのINSERT処理
        $this->execLogInserts($logRepositories);
        
        // クリア
        $this->clear();
    }

    /**
     * ログRepositoryのINSERT処理（バッチINSERT）
     *
     * @param array $repositories
     * @return void
     */
    private function execLogInserts(array $repositories): void
    {
        $insertsByTable = [];
        
        foreach ($repositories as $repository) {
            $connection = $repository->getConnection();
            $table = $repository->getTableName();
            $models = $repository->getQueuedModels();
            
            $key = "{$connection}.{$table}";
            
            if (!isset($insertsByTable[$key])) {
                $insertsByTable[$key] = [
                    'connection' => $connection,
                    'table' => $table,
                    'records' => [],
                ];
            }
            
            foreach ($models as $model) {
                $insertsByTable[$key]['records'][] = $model->getAttributes();
            }
        }
        
        // バッチINSERT実行
        foreach ($insertsByTable as $item) {
            if (empty($item['records'])) {
                continue;
            }
            
            DB::connection($item['connection'])
                ->table($item['table'])
                ->insert($item['records']);
        }
    }

    /**
     * モデルのWHERE条件を構築
     * プライマリキーを使用（複合主キーにも対応）
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return array
     */
    private function buildWhereCondition($model): array
    {
        $primaryKey = $model->getKeyName();
        
        // 複合主キーの場合
        if (is_array($primaryKey)) {
            $where = [];
            foreach ($primaryKey as $key) {
                $where[$key] = $model->getAttribute($key);
            }
            return $where;
        }
        
        // 単一主キーの場合
        return [$primaryKey => $model->getKey()];
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

    /**
     * 溜め込んでいるモデルの数を取得（デバッグ用）
     *
     * @return array
     */
    public function getQueueCount(): array
    {
        $total = 0;
        $purchaseLogCount = 0;
        
        foreach ($this->repositories as $repository) {
            $total += count($repository->getQueuedModels());
        }
        
        foreach ($this->purchaseLogRepositories as $repository) {
            $purchaseLogCount += count($repository->getQueuedModels());
        }
        
        return [
            'repositories' => count($this->repositories),
            'purchase_log_repositories' => count($this->purchaseLogRepositories),
            'models' => $total,
            'purchase_log_models' => $purchaseLogCount,
        ];
    }
}
