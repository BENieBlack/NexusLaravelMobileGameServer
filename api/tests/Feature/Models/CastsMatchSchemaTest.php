<?php

namespace Tests\Feature\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * $casts が実際のカラム型と食い違っていないことを検証する
 *
 * 誤ったキャストは例外を出さずに値を壊す。実際に次の不具合が起きていた。
 *
 * - MstInAppPurchaseEffect::effect_type を integer にしていたため
 *   'ExpBoost' が '0' になり、購入したパスの効果が一切効かなかった
 * - MstInAppPurchaseEffect::value を integer にしていたため 1.50 が 1 になっていた
 * - LogUnit::mst_unit_id を integer にしていたため 'unit_xxx' が 0 で記録されていた
 * - MstItem::value を integer にしていたため double の小数が落ちていた
 */
class CastsMatchSchemaTest extends TestCase
{
    /**
     * モデルのディレクトリ => 確認に使うDB接続
     */
    private const MODEL_DIRECTORIES = [
        'Mst' => 'mst',
        'Trx' => 'trx1',
        'Sys' => 'sys',
        'Log' => 'log1',
    ];

    /**
     * キャスト => 許容するDBのデータ型
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_DB_TYPES = [
        'integer' => ['int', 'bigint', 'smallint', 'tinyint', 'mediumint'],
        'int' => ['int', 'bigint', 'smallint', 'tinyint', 'mediumint'],
        'boolean' => ['tinyint', 'bit'],
        'float' => ['double', 'float', 'decimal'],
        'double' => ['double', 'float', 'decimal'],
        'array' => ['json', 'text', 'longtext'],
        'json' => ['json', 'text', 'longtext'],
    ];

    #[Test]
    public function すべてのモデルのキャストがカラム型と一致する(): void
    {
        $mismatches = [];

        foreach (self::MODEL_DIRECTORIES as $directory => $connection) {
            foreach ($this->modelsIn($directory) as $model) {
                $columnTypes = $this->columnTypes($connection, $model->getTable());

                if ($columnTypes === []) {
                    continue;
                }

                foreach ($model->getCasts() as $column => $cast) {
                    $dbType = $columnTypes[$column] ?? null;

                    if ($dbType === null || $this->isAllowed($cast, $dbType)) {
                        continue;
                    }

                    $mismatches[] = sprintf(
                        '%s::$%s は cast=%s だが、DBは %s',
                        $model::class,
                        $column,
                        $cast,
                        $dbType
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $mismatches,
            "キャストとカラム型が食い違っている:\n".implode("\n", $mismatches)
            ."\n誤ったキャストは例外にならず値だけが壊れるため、必ず直すこと。"
        );
    }

    /**
     * @return list<Model>
     */
    private function modelsIn(string $directory): array
    {
        $models = [];

        foreach (glob(app_path("Models/{$directory}/*.php")) ?: [] as $file) {
            $class = 'App\\Models\\'.$directory.'\\'.basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract() || $reflection->isInterface() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            $models[] = new $class;
        }

        return $models;
    }

    /**
     * @return array<string, string> カラム名 => DBのデータ型
     */
    private function columnTypes(string $connection, string $table): array
    {
        $columns = DB::connection($connection)->select(
            'SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        $types = [];

        foreach ($columns as $column) {
            $types[$column->COLUMN_NAME] = $column->DATA_TYPE;
        }

        return $types;
    }

    /**
     * このキャストがそのカラム型に対して妥当か
     */
    private function isAllowed(string $cast, string $dbType): bool
    {
        // decimal:2 のような指定
        if (str_starts_with($cast, 'decimal')) {
            return in_array($dbType, ['decimal', 'float', 'double'], true);
        }

        // datetime:Y-m-d H:i:s のような指定。日時はstringで扱う方針のため
        // キャスト自体を推奨しないが、型の妥当性だけ見る
        if (str_starts_with($cast, 'datetime') || str_starts_with($cast, 'immutable_datetime')) {
            return in_array($dbType, ['datetime', 'timestamp', 'date'], true);
        }

        $allowed = self::ALLOWED_DB_TYPES[$cast] ?? null;

        // string や未知のキャストは対象外（文字列化はどの型でも成立する）
        return $allowed === null || in_array($dbType, $allowed, true);
    }
}
