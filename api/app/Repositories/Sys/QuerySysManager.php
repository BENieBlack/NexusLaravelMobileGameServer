<?php

namespace App\Repositories\Sys;

use Illuminate\Support\Facades\DB;

/**
 * QuerySysManager
 * 
 * システムデータベース（sys）への変更を溜め込み、一括で実行する
 * Unit of Work パターンの実装
 * 
 * Repositoryを登録し、実行時に各Repositoryからモデルを取り出してINSERT/UPDATEを判定する
 * 
 * 重要：sysデータベースでは、INSERTしたIDを外部キーとして使用するため、
 * バッチインサートではなく個別インサートを行い、IDを取得する
 */
class QuerySysManager
{
    /**
     * 登録されたRepositoryのリスト
     * 
     * @var array<_BaseSysRepository>
     */
    private array $repositories = [];

    /**
     * Repositoryを登録する
     *
     * @param _BaseSysRepository $repository
     * @return void
     */
    public function registerRepository(_BaseSysRepository $repository): void
    {
        // 重複登録を防ぐ（同じインスタンスは1回のみ登録）
        $hash = spl_object_hash($repository);
        
        if (!isset($this->repositories[$hash])) {
            $this->repositories[$hash] = $repository;
        }
    }

    /**
     * 溜め込んだ全てのモデルを実行する
     * 各Repositoryからモデルを取り出し、実行時にINSERT/UPDATEを判定
     * 
     * sysデータベースでは、INSERTしたIDを取得して外部キーとして使用するため、
     * 個別インサートを行う
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
        
        // 各Repositoryからモデルを取り出す
        foreach ($this->repositories as $repository) {
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
        
        // INSERT処理（個別インサートでIDを取得）
        foreach ($insertsByTable as $item) {
            if (empty($item['records'])) {
                continue;
            }
            
            $models = $item['models'];
            $records = $item['records'];
            $originalStates = $item['originalStates'];
            $repository = $item['repository'];
            
            // 各モデルを個別にINSERTしてIDを取得
            foreach ($models as $index => $model) {
                $attributes = $records[$index];
                
                // INSERTを実行してIDを取得
                $id = DB::connection($item['connection'])
                    ->table($item['table'])
                    ->insertGetId($attributes);
                
                // モデルにIDをセット
                $model->setAttribute($model->getKeyName(), $id);
                $model->exists = true;
                
                // afterSaveフックを呼び出す（INSERT）
                $originalState = $originalStates[$index] ?? [];
                $repository->afterSave($model, $originalState);
            }
        }
        
        // UPDATE処理
        foreach ($updates as $item) {
            $model = $item['model'];
            $where = $this->buildWhereCondition($model);
            
            DB::connection($item['connection'])
                ->table($item['table'])
                ->where($where)
                ->update($item['data']);
            
            // afterSaveフックを呼び出す（UPDATE）
            $item['repository']->afterSave($model, $item['originalState']);
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
        
        // クリア
        $this->clear();
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
        
        $this->repositories = [];
    }

    /**
     * 溜め込んでいるモデルの数を取得（デバッグ用）
     *
     * @return array
     */
    public function getQueueCount(): array
    {
        $total = 0;
        
        foreach ($this->repositories as $repository) {
            $total += count($repository->getQueuedModels());
        }
        
        return [
            'repositories' => count($this->repositories),
            'models' => $total,
        ];
    }
}
