<?php

namespace App\Persistence\QueryManager;

use App\Repositories\_BaseRepository;
use App\Repositories\Log\_BaseLogRepository;
use App\Repositories\Sys\_BaseSysRepository;
use App\Repositories\Trx\_BaseTrxRepository;
use Illuminate\Support\Collection;

/**
 * OperationCollector
 * 
 * Repositoryからモデルを収集し、INSERT/UPDATE/DELETEごとにグループ化する
 */
class OperationCollector
{
    /**
     * Repositoryのリストから操作を収集
     *
     * @param array<_BaseRepository> $repositories
     * @return array ['inserts' => array, 'updates' => array, 'deletes' => array, 'logs' => array]
     */
    public function collect(array $repositories): array
    {
        $insertsByTable = [];
        $updates = [];
        $deletes = [];
        $logRepositories = [];
        
        foreach ($repositories as $repository) {
            // LogRepositoryは別処理
            if ($repository instanceof _BaseLogRepository) {
                $logRepositories[] = $repository;
                continue;
            }
            
            // TrxRepositoryまたはSysRepository
            /** @var _BaseTrxRepository|_BaseSysRepository $repository */
            $connection = $repository->getConnection();
            $table = $repository->getTableName();
            $models = $repository->getQueuedModels();
            $originalStates = $repository->getOriginalStates();
            
            // INSERT/UPDATEの分類
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
            
            // DELETE対象のモデルを取り出す
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
        
        return [
            'inserts' => $insertsByTable,
            'updates' => $updates,
            'deletes' => $deletes,
            'logs' => $logRepositories,
        ];
    }
    
    /**
     * ログRepositoryから操作を収集
     *
     * @param array<_BaseLogRepository> $logRepositories
     * @return array
     */
    public function collectLogs(array $logRepositories): array
    {
        $insertsByTable = [];
        
        foreach ($logRepositories as $repository) {
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
        
        return $insertsByTable;
    }
}
