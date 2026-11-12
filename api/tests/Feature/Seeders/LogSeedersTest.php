<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\LogAccessSeeder;
use Database\Seeders\LogGachaSeeder;
use Database\Seeders\LogInAppPurchaseSeeder;
use Database\Seeders\LogItemSeeder;
use Database\Seeders\LogPlayerSeeder;
use Database\Seeders\LogUnitSeeder;
use Database\Seeders\MstItemSeeder;
use Database\Seeders\MstUnitSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 開発用のログのシーダー（make seed の Phase 4）のテスト。
 *
 * 以前は LogAccessSeeder と LogInAppPurchaseSeeder が実在しない接続 'log' を使い、
 * make seed が「Database connection [log] not configured」で止まっていた。
 * log_* はプレイヤーと同じシャード DB にあるので、接続 trx（シャード 1）で動くことを確かめる。
 *
 * シーダーは truncate する（MySQL では暗黙コミットでロールバックできない）ので、
 * トランザクションで包まず、触るテーブルを tearDown で空に戻す。
 */
class LogSeedersTest extends TestCase
{
    use RefreshMultipleDatabases;

    /** シーダーが truncate / 挿入するシャード側のテーブル（trx 接続） */
    private const LOG_TABLES = [
        'log_action_api_access',
        'log_action_in_app_purchase',
        'log_action_player_update',
        'log_action_item_change',
        'log_action_unit_levelup',
        'log_action_gacha_draw',
    ];

    public function beginDatabaseTransaction(): void
    {
        // シーダーの truncate が暗黙コミットになるため、自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUp();

        // 1 年分（80 万行規模）はテストには重いので 2 日分に絞る
        config(['seeders.log_access_days' => 2]);

        // ガチャ・アイテム・ユニットのログが参照するマスターと、ユニットの所持
        $this->seed(MstItemSeeder::class);
        $this->seed(MstUnitSeeder::class);
        $this->insertPlayerUnits();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    #[Test]
    public function アクセスログは接続trxに入り_2回流しても前回の行が残らない(): void
    {
        // 操作: 2 回流す
        $this->seed(LogAccessSeeder::class);
        $first = $this->rowCount('log_action_api_access');
        $this->seed(LogAccessSeeder::class);

        // 期待: 行が入り、truncate されている（auto increment が 1 から振り直される）
        $this->assertGreaterThan(0, $first);
        $this->assertGreaterThan(0, $this->rowCount('log_action_api_access'));
        $this->assertSame(1, (int) DB::connection('trx')->table('log_action_api_access')->min('id'));
    }

    #[Test]
    public function 課金ログはアクセスログと同じ数だけ入り_2回流しても同じ数になる(): void
    {
        $this->seed(LogAccessSeeder::class);
        $expected = DB::connection('trx')->table('log_action_api_access')
            ->where('endpoint', '/api/shop/purchase')->count();

        $this->seed(LogInAppPurchaseSeeder::class);
        $this->seed(LogInAppPurchaseSeeder::class);

        $this->assertGreaterThan(0, $expected);
        $this->assertSame($expected, $this->rowCount('log_action_in_app_purchase'));
    }

    #[Test]
    public function プレイヤー_アイテム_ユニット_ガチャのログが入り_2回流しても増えない(): void
    {
        $this->seed(LogAccessSeeder::class);

        foreach ([
            LogPlayerSeeder::class => 'log_action_player_update',
            LogItemSeeder::class => 'log_action_item_change',
            LogUnitSeeder::class => 'log_action_unit_levelup',
            LogGachaSeeder::class => 'log_action_gacha_draw',
        ] as $seeder => $table) {
            $this->seed($seeder);
            $first = $this->rowCount($table);
            $this->seed($seeder);

            $this->assertGreaterThan(0, $first, "{$seeder} が {$table} に行を入れていない");
            $this->assertSame($first, $this->rowCount($table), "{$seeder} を 2 回流すと {$table} の行数が変わった");
        }
    }

    private function rowCount(string $table): int
    {
        return DB::connection('trx')->table($table)->count();
    }

    /** LogUnitSeeder が参照する trx_unit を、アクセスログのプレイヤー（1〜1000）分だけ作る */
    private function insertPlayerUnits(): void
    {
        $mstUnitId = DB::connection('mst')->table('mst_unit')->value('id');
        $now = '2026-01-01 00:00:00';
        $rows = [];
        for ($playerId = 1; $playerId <= 1000; $playerId++) {
            $rows[] = [
                'sys_player_id' => $playerId,
                'mst_unit_id' => $mstUnitId,
                'grade' => 1,
                'level' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::connection('trx1')->table('trx_unit')->insert($chunk);
        }
    }

    private function cleanUp(): void
    {
        foreach (self::LOG_TABLES as $table) {
            DB::connection('trx')->table($table)->truncate();
        }
        DB::connection('trx1')->table('trx_unit')->truncate();
        DB::connection('trx2')->table('trx_unit')->truncate();
        DB::connection('mst')->table('mst_item')->delete();
        DB::connection('mst')->table('mst_unit')->delete();
    }
}
