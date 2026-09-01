<?php

namespace NexusUnitOfWork\Persistence\QueryManager;

use Illuminate\Support\Facades\DB;

/**
 * BatchExecutor
 *
 * データベース操作の実行を担当するクラス
 */
class BatchExecutor
{
    private UpdateQueryBuilder $queryBuilder;

    public function __construct()
    {
        $this->queryBuilder = new UpdateQueryBuilder;
    }

    /**
     * INSERT操作を実行
     *
     * @param  array<string, array{connection: string, table: string, records: array<int, array<string, mixed>>, models: array<int, mixed>, repository: mixed, originalStates: array<int, array<string, mixed>>}>  $insertsByTable
     * @return void
     */
    public function executeInserts(array $insertsByTable): void
    {
        foreach ($insertsByTable as $item) {
            if (empty($item['records'])) {
                continue;
            }

            $models = $item['models'];
            $records = $item['records'];
            $originalStates = $item['originalStates'];
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

                    // afterSaveフックを呼び出す
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

                // モデルにexists = trueとIDを設定し、afterSaveフックを呼び出す
                foreach ($models as $index => $model) {
                    $model->exists = true;

                    // auto-incrementのIDを設定（複数レコードの場合は連番）
                    // lastInsertId()は文字列を返すため、加算前に数値へ変換する
                    if ($firstId && $model->getIncrementing()) {
                        $model->setAttribute($model->getKeyName(), (int) $firstId + $index);
                    }

                    // created_at / updated_at はDB側のDEFAULT CURRENT_TIMESTAMPで
                    // 採番されるため、INSERT後にモデルへ読み戻す
                    $this->refreshTimestamps($model, $item['connection'], $table);

                    $originalState = $originalStates[$index] ?? [];
                    $repository->afterSave($model, $originalState);
                }
            }
        }
    }

    /**
     * DB側で採番された created_at / updated_at をモデルへ読み戻す
     *
     * @param  mixed  $model
     * @param  string  $connection
     * @param  string  $table
     * @return void
     */
    private function refreshTimestamps($model, string $connection, string $table): void
    {
        $key = $model->getKeyName();

        // 複合主キーなど、単一キーで特定できないモデルは対象外
        if (! is_string($key) || $key === '') {
            return;
        }

        $id = $model->getAttribute($key);

        if ($id === null) {
            return;
        }

        $row = DB::connection($connection)
            ->table($table)
            ->where($key, $id)
            ->first(['created_at', 'updated_at']);

        if ($row === null) {
            return;
        }

        foreach (['created_at', 'updated_at'] as $column) {
            if (property_exists($row, $column)) {
                $model->setAttribute($column, $row->{$column});
            }
        }

        $model->syncOriginal();
    }

    /**
     * UPDATE操作を実行
     *
     * @param  array<int, array{connection: string, table: string, model: mixed, data: array<string, mixed>, repository: mixed, uniqueKey: string, originalState: array<string, mixed>}>  $updates
     * @return void
     */
    public function executeUpdates(array $updates): void
    {
        foreach ($updates as $item) {
            $repository = $item['repository'];
            $model = $item['model'];
            $where = $this->queryBuilder->buildWhereCondition($model);

            // _BaseTrxモデルの場合、相対的な変更をチェック
            $hasRelativeChanges = method_exists($model, 'getRelativeChanges')
                && method_exists($model, 'hasRelativeChanges')
                && $model->hasRelativeChanges();

            if ($hasRelativeChanges) {
                $this->executeRelativeUpdate($item, $where);
            } else {
                $this->executeStandardUpdate($item, $where);
            }

            // afterSaveフックを呼び出す
            $repository->afterSave($model, $item['originalState']);
        }
    }

    /**
     * 相対的な更新を実行
     *
     * @param  array{connection: string, table: string, model: mixed, data: array<string, mixed>, repository: mixed, uniqueKey: string, originalState: array<string, mixed>}  $item
     * @param  array<string, mixed>  $where
     * @return void
     */
    private function executeRelativeUpdate(array $item, array $where): void
    {
        $model = $item['model'];
        $relativeChanges = $model->getRelativeChanges();

        // UPDATE文を構築
        $result = $this->queryBuilder->buildRelativeUpdate(
            $item['table'],
            $item['data'],
            $relativeChanges,
            $where
        );

        // SQL文を実行
        if (! empty($result['bindings']) || ! empty($relativeChanges)) {
            DB::connection($item['connection'])->update($result['sql'], $result['bindings']);
        }

        // 相対的な変更をクリア
        $model->clearRelativeChanges();
    }

    /**
     * 通常のUPDATEを実行
     *
     * @param  array{connection: string, table: string, model: mixed, data: array<string, mixed>, repository: mixed, uniqueKey: string, originalState: array<string, mixed>}  $item
     * @param  array<string, mixed>  $where
     * @return void
     */
    private function executeStandardUpdate(array $item, array $where): void
    {
        DB::connection($item['connection'])
            ->table($item['table'])
            ->where($where)
            ->update($item['data']);
    }

    /**
     * DELETE操作を実行
     *
     * @param  array<int, array{connection: string, table: string, model: mixed}>  $deletes
     * @return void
     */
    public function executeDeletes(array $deletes): void
    {
        foreach ($deletes as $item) {
            $model = $item['model'];
            $where = $this->queryBuilder->buildWhereCondition($model);

            DB::connection($item['connection'])
                ->table($item['table'])
                ->where($where)
                ->delete();
        }
    }

    /**
     * ログのINSERT操作を実行
     *
     * @param  array<string, array{connection: string, table: string, records: array<int, array<string, mixed>>}>  $insertsByTable
     * @return void
     */
    public function executeLogInserts(array $insertsByTable): void
    {
        foreach ($insertsByTable as $item) {
            if (empty($item['records'])) {
                continue;
            }

            DB::connection($item['connection'])
                ->table($item['table'])
                ->insert($item['records']);
        }
    }
}
