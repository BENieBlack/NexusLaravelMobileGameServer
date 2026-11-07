<?php

namespace Tests\Feature\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * モデルが書いている列が、マイグレーションで作ったテーブルに実在するか
 *
 * テーブルの定義は packages/ と api/ のマイグレーションに、Eloquent のモデルは api/app/Models にあり、
 * 2か所に分かれている。マイグレーションで列を消したり名前を変えたりしても、モデルが古い列を指したままだと、
 * 実行時（INSERT / UPDATE）まで気づけない。
 *
 * 規約: モデルは実在するカラムだけを @property / $fillable / $casts に書く（CLAUDE.md）。
 * テスト用に migrate 済みのDBに対して、全てのモデルの次を確かめる。
 *
 * - テーブルが存在する
 * - $fillable の列が、テーブルにある
 * - 宣言した $casts の列が、テーブルにある
 * - docblock の @property の名前が、テーブルにある
 */
class ModelColumnsMatchMigrationsTest extends TestCase
{
    use RefreshMultipleDatabases;

    /** 名前空間 → テーブルを引く接続（trx / log は、どのシャードも同じ定義なので trx1 で見る） */
    private const CONNECTIONS = [
        'Mst' => 'mst',
        'Sys' => 'sys',
        'Trx' => 'trx1',
        'Log' => 'trx1',
    ];

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function models(): array
    {
        $cases = [];

        foreach (array_keys(self::CONNECTIONS) as $group) {
            foreach (glob(dirname(__DIR__, 3)."/app/Models/{$group}/*.php") ?: [] as $file) {
                $class = "App\\Models\\{$group}\\".basename($file, '.php');

                if (! class_exists($class)) {
                    continue;
                }

                $reflection = new \ReflectionClass($class);

                // 基底クラス（_BaseMst など）は、テーブルを持たない
                if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                    continue;
                }

                $cases["{$group}\\".basename($file, '.php')] = [$class];
            }
        }

        return $cases;
    }

    /**
     * @param  class-string<Model>  $class
     */
    #[Test]
    #[DataProvider('models')]
    public function test_model_attributes_exist_as_columns(string $class): void
    {
        $reflection = new \ReflectionClass($class);
        $group = basename(dirname(str_replace('\\', '/', $class)));
        $connection = self::CONNECTIONS[$group];

        /** @var Model $model */
        $model = new $class;
        $table = $model->getTable();

        $this->assertTrue(
            Schema::connection($connection)->hasTable($table),
            "{$class} のテーブル {$table} が、{$connection} 接続にありません"
        );

        $columns = Schema::connection($connection)->getColumnListing($table);
        $declared = $this->declaredAttributes($reflection);

        $missing = [];
        foreach ($declared as $source => $names) {
            foreach ($names as $name) {
                if (! in_array($name, $columns, true)) {
                    $missing[] = "{$source}: {$name}";
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            "{$class} が、テーブル {$table} に無い列を書いています:\n".implode("\n", $missing)."\n"
            .'実在する列だけを書いてください。リレーションやアクセサの名前は @property に書かず、必要なら @property-read にします。'
        );
    }

    /**
     * モデルが、列として宣言している名前
     *
     * @param  \ReflectionClass<Model>  $reflection
     * @return array<string, list<string>>
     */
    private function declaredAttributes(\ReflectionClass $reflection): array
    {
        $defaults = $reflection->getDefaultProperties();

        // $fillable / $casts は、親クラスの宣言ではなく、そのモデル自身（とトレイト）の宣言を見る
        $fillable = array_values(array_filter((array) ($defaults['fillable'] ?? []), 'is_string'));
        $casts = array_keys((array) ($defaults['casts'] ?? []));

        $docProperties = [];
        $doc = $reflection->getDocComment() ?: '';
        if (preg_match_all('/@property\s+\S+\s+\$(\w+)/', $doc, $matches)) {
            $docProperties = $matches[1];
        }

        return [
            '$fillable' => $fillable,
            '$casts' => $casts,
            '@property' => $docProperties,
        ];
    }
}
