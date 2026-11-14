<?php

namespace Tests\Feature\Seeders;

use App\Domain\MstConfig\Constants\MstConfigKey;
use App\Repositories\Mst\MstConfigRepository;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MstConfigSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 設定マスターのシーダー（MstConfigSeeder）と、マイグレーション後の mst_config の構造のテスト。
 *
 * シーダーは truncate（暗黙コミット）するので、トランザクションで包まず、行は tearDown で消す。
 */
class MstConfigSeederTest extends TestCase
{
    use RefreshMultipleDatabases;

    public function beginDatabaseTransaction(): void
    {
        // シーダーの truncate が暗黙コミットになるため、自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    #[Test]
    public function mst_config_keyの全caseの行を既定値と同じ初期値で作る(): void
    {
        (new MstConfigSeeder)->run();

        $rows = DB::connection('mst')->table('mst_config')->get()->keyBy('id');

        $this->assertCount(count(MstConfigKey::cases()), $rows);

        foreach (MstConfigKey::cases() as $key) {
            $row = $rows[$key->value];
            $this->assertSame($key->valueType()->value, $row->value_type);
            $this->assertSame($key->initialValue(), $row->value);
            $this->assertNotSame('', $row->description);
        }

        // 回復間隔は従来の 300 秒
        $this->assertSame('300', $rows['stamina_recovery_interval_seconds']->value);
        $this->assertSame('int', $rows['stamina_recovery_interval_seconds']->value_type);
    }

    #[Test]
    public function 何度流しても同じ結果になる(): void
    {
        (new MstConfigSeeder)->run();
        $first = $this->snapshot();

        // 運営ツールでの変更や余計な行があっても、truncate して作り直す
        DB::connection('mst')->table('mst_config')->where('id', 'stamina_recovery_interval_seconds')->update(['value' => '999']);
        DB::connection('mst')->table('mst_config')->insert([
            'deploy_key' => 202601010,
            'id' => 'extra_config',
            'value' => 'x',
            'value_type' => 'string',
            'description' => '余計な行',
        ]);

        (new MstConfigSeeder)->run();
        $second = $this->snapshot();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function 基本のシードのphase1に入っている(): void
    {
        $source = (string) file_get_contents((string) (new ReflectionClass(DatabaseSeeder::class))->getFileName());

        $this->assertStringContainsString('MstConfigSeeder::class', $source);
    }

    #[Test]
    public function マイグレーション後のmst_configは文字列idの主キーで_aut_o_incremen_tを持たない(): void
    {
        $this->assertTrue(Schema::connection('mst')->hasTable('mst_config'));
        $this->assertSame(
            ['deploy_key', 'id', 'value', 'value_type', 'description', 'created_at', 'updated_at'],
            Schema::connection('mst')->getColumnListing('mst_config')
        );

        $columns = collect(Schema::connection('mst')->getColumns('mst_config'))->keyBy('name');

        $this->assertStringStartsWith('varchar', $columns['id']['type']);
        $this->assertFalse($columns['id']['auto_increment']);
        $this->assertSame("enum('int','float','bool','string')", $columns['value_type']['type']);

        // 全カラムにコメントがある
        foreach ($columns as $name => $column) {
            $this->assertNotEmpty($column['comment'], "{$name} にコメントが無い");
        }

        $primary = collect(Schema::connection('mst')->getIndexes('mst_config'))->firstWhere('primary', true);
        $this->assertSame(['id'], $primary['columns']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function snapshot(): array
    {
        return DB::connection('mst')->table('mst_config')->orderBy('id')
            ->get(['deploy_key', 'id', 'value', 'value_type', 'description'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_config')->truncate();
        app(MstConfigRepository::class)->clearCache();
    }
}
