<?php

namespace Nexus\Core\UnitOfWork;

use Nexus\Core\Models\_BaseModel;
use Nexus\Core\UnitOfWork\Contracts\ReturnedAsSingleUpdatedResource;
use Nexus\Core\UnitOfWork\Contracts\ReturnedAsUpdatedResource;

/**
 * UpdatedResources
 *
 * 1回の処理（UnitOfWork）で、INSERT / UPDATE されたモデルを、テーブルごとにまとめて持つ。
 * クライアントに「何が変わったか」を返すためのもの。更新されたものだけが入る
 * （trx_unit_list、trx_item_list のように、テーブル名 + _list がキーになる）。
 *
 * - 対象は、ReturnedAsUpdatedResource を実装したモデルだけ（許可リスト方式）。
 *   実装していないテーブル（ログ、内訳、履歴など）は、更新されても入らない
 * - 同じ行が複数回更新されたら、1件にまとめる（最後の状態を持つ）
 * - 物理削除した行は含まない（論理削除 is_delete は更新なので含む）
 * - モデルの値は、書き込んだ時点のメモリ上の値。相対更新（free_amount + 5 のSQL）は、
 *   同時に別の処理が同じ行を更新していると、DBの値とずれることがある
 */
final class UpdatedResources
{
    /**
     * @var array<string, array<string, _BaseModel>> テーブル名 => 行のキー => モデル
     */
    private array $models = [];

    /**
     * 更新されたモデルを記録する
     *
     * ReturnedAsUpdatedResource を実装していないモデルは、記録しない。
     * 同じ行（同じテーブルの同じキー）は、後から記録したもので置き換える。
     */
    public function record(_BaseModel $model): void
    {
        if (! $model instanceof ReturnedAsUpdatedResource) {
            return;
        }

        $this->models[$model->getTable()][$this->rowKey($model)] = $model;
    }

    /**
     * UnitOfWork が実行した操作（INSERT / UPDATE）から、記録する（物理削除は含めない）
     *
     * @param  array{inserts: array<string, array{models: array<int, mixed>}>, updates: array<int, array{model: mixed}>}  $operations
     */
    public function recordOperations(array $operations): void
    {
        foreach ($operations['inserts'] as $insert) {
            foreach ($insert['models'] as $model) {
                if ($model instanceof _BaseModel) {
                    $this->record($model);
                }
            }
        }

        foreach ($operations['updates'] as $update) {
            if ($update['model'] instanceof _BaseModel) {
                $this->record($update['model']);
            }
        }
    }

    public function isEmpty(): bool
    {
        return $this->models === [];
    }

    /**
     * 更新があったテーブル名
     *
     * @return list<string>
     */
    public function getTables(): array
    {
        return array_keys($this->models);
    }

    /**
     * 指定したテーブルの、更新されたモデル
     *
     * @return list<_BaseModel>
     */
    public function getByTable(string $table): array
    {
        return array_values($this->models[$table] ?? []);
    }

    /**
     * APIのレスポンス用の配列にする
     *
     * 1行しかないテーブル（ReturnedAsSingleUpdatedResource）は、'{テーブル名}' => オブジェクト1件。
     * それ以外は、'{テーブル名}_list' => 各行の toResponseArray() の配列。
     *
     * @return array<string, array<mixed>>
     */
    public function toResponseArray(): array
    {
        $result = [];

        foreach ($this->models as $table => $models) {
            $models = array_values($models);

            if ($models[0] instanceof ReturnedAsSingleUpdatedResource) {
                $result[$table] = $models[array_key_last($models)]->toResponseArray();

                continue;
            }

            $result[$table.'_list'] = array_map(
                fn (_BaseModel $model) => $model->toResponseArray(),
                $models
            );
        }

        return $result;
    }

    /**
     * 行を一意に決めるキー（主キーの値をつなげたもの）
     *
     * 主キーがまだ空のモデルは、モデルのインスタンスで区別する。
     */
    private function rowKey(_BaseModel $model): string
    {
        $values = [];
        foreach ($model->getUniqueKeys() as $column) {
            $value = $model->getAttribute($column);
            $values[] = is_scalar($value) ? (string) $value : '';
        }

        if (array_filter($values, fn (string $value) => $value !== '') === []) {
            return 'object:'.spl_object_id($model);
        }

        return implode(':', $values);
    }
}
