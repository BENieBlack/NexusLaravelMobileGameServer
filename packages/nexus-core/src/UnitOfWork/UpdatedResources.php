<?php

namespace Nexus\Core\UnitOfWork;

use Nexus\Core\Models\Trx\_BaseTrx;

/**
 * UpdatedResources
 *
 * 1回の処理（UnitOfWork）で、INSERT / UPDATE された trx のモデルを、テーブルごとにまとめて持つ。
 * クライアントに「何が変わったか」を返すためのもの。更新されたものだけが入る
 * （trx_unit_list、trx_item_list のように、テーブル名 + _list がキーになる）。
 *
 * - 同じ行が複数回更新されたら、1件にまとめる（最後の状態を持つ）
 * - 物理削除した行は含まない（論理削除 is_delete は更新なので含む）
 * - ログ（log_*）と sys のテーブルは含まない
 * - モデルの値は、書き込んだ時点のメモリ上の値。相対更新（free_amount + 5 のSQL）は、
 *   同時に別の処理が同じ行を更新していると、DBの値とずれることがある
 */
final class UpdatedResources
{
    /**
     * @var array<string, array<string, _BaseTrx>> テーブル名 => 行のキー => モデル
     */
    private array $models = [];

    /**
     * 更新されたモデルを記録する
     *
     * 同じ行（同じテーブルの同じキー）は、後から記録したもので置き換える。
     */
    public function record(_BaseTrx $model): void
    {
        $this->models[$model->getTable()][$this->rowKey($model)] = $model;
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
     * @return list<_BaseTrx>
     */
    public function getByTable(string $table): array
    {
        return array_values($this->models[$table] ?? []);
    }

    /**
     * APIのレスポンス用の配列にする
     *
     * @return array<string, list<array<string, mixed>>> '{テーブル名}_list' => 各行の toResponseArray()
     */
    public function toResponseArray(): array
    {
        $result = [];

        foreach ($this->models as $table => $models) {
            $result[$table.'_list'] = array_map(
                fn (_BaseTrx $model) => $model->toResponseArray(),
                array_values($models)
            );
        }

        return $result;
    }

    /**
     * 行を一意に決めるキー（主キーの値をつなげたもの）
     *
     * 主キーがまだ空のモデルは、モデルのインスタンスで区別する。
     */
    private function rowKey(_BaseTrx $model): string
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
