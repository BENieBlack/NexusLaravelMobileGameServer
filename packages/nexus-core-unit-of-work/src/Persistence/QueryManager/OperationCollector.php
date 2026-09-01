<?php

namespace NexusUnitOfWork\Persistence\QueryManager;

use Illuminate\Database\Eloquent\Model;
use Nexus\Core\Models\Log\_BaseLog;
use Nexus\Core\Models\Sys\_BaseSys;
use Nexus\Core\Models\Trx\_BaseTrx;
use Nexus\Core\Repositories\_BaseRepository;
use Nexus\Core\Repositories\Log\_BaseLogRepository;
use Nexus\Core\Repositories\Sys\_BaseSysRepository;
use Nexus\Core\Repositories\Trx\_BaseTrxRepository;

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
     * @param  array<_BaseRepository<array-key, Model>>  $repositories
     * @return array{
     *     inserts: array<string, array{connection: string, table: string, records: array<int, array<string, mixed>>, models: array<int, mixed>, repository: mixed, originalStates: array<int, array<string, mixed>>}>,
     *     updates: array<int, array{connection: string, table: string, model: mixed, data: array<string, mixed>, repository: mixed, uniqueKey: string, originalState: array<string, mixed>}>,
     *     deletes: array<int, array{connection: string, table: string, model: mixed}>,
     *     logs: array<int, _BaseLogRepository<_BaseLog>>
     * }
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
            /** @var _BaseTrxRepository<_BaseTrx>|_BaseSysRepository<_BaseSys> $repository */
            $connection = $repository->getConnection();
            $table = $repository->getTableName();
            $models = $repository->getQueuedModels();
            $originalStates = $repository->getOriginalStates();

            // INSERT/UPDATEの分類
            foreach ($models as $uniqueKey => $model) {
                if ($model->exists) {
                    // 既存モデル → UPDATE
                    $dirtyAttributes = $model->getDirty();

                    if (! empty($dirtyAttributes)) {
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

                    if (! isset($insertsByTable[$key])) {
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

            // DELETE対象のモデルを取り出す（Logは冒頭でcontinue済み）
            $deleteModels = $repository->getQueuedDeleteModels();
            foreach ($deleteModels as $model) {
                $deletes[] = [
                    'connection' => $connection,
                    'table' => $table,
                    'model' => $model,
                ];
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
     * @param  array<int, _BaseLogRepository<_BaseLog>>  $logRepositories
     * @return array<string, array{connection: string, table: string, records: array<int, array<string, mixed>>}>
     */
    public function collectLogs(array $logRepositories): array
    {
        $insertsByTable = [];

        foreach ($logRepositories as $repository) {
            $connection = $repository->getConnection();
            $table = $repository->getTableName();
            $models = $repository->getQueuedModels();

            $key = "{$connection}.{$table}";

            if (! isset($insertsByTable[$key])) {
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
