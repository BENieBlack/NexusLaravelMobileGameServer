<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\LogUnitSeeder;
use Database\Seeders\MstUnitSeeder;
use Database\Seeders\TrxItemSeeder;
use Database\Seeders\TrxPlayerSeeder;
use Database\Seeders\TrxUnitSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * Trx / Log 系のシーダーが、シャード数に従って全シャードを走査することのテスト。
 *
 * 以前は TrxUnitSeeder / TrxItemSeeder / LogUnitSeeder が ['trx1', 'trx2'] をハードコードし、
 * TrxPlayerSeeder も 2 件固定の config('sharding.transaction.nodes') を見ていたため、
 * DB_SHARD_COUNT=3 の環境で trx3 のプレイヤーが対象外だった。
 * 今は config('database.pitr.active_trx_connections')（DB_SHARD_COUNT から作られる一覧。
 * ShardAssignmentService と同じ）を見る。
 *
 * テストの環境は 2 シャード固定（phpunit.xml）で、CI には db-shard3 が無い（ホスト名を解決できない）。
 * そこで config を差し替えて 3 シャードにし、trx3 は trx2 と同じホスト・DB を指すエイリアスにする。
 * 物理 DB は trx2 と共有になるので、「trx3 の接続名が走査された・書かれた」ことは
 * DB::listen で実行されたクエリの接続名から確かめ、作った行は tearDown で消す。
 *
 * シーダーは truncate する（暗黙コミット）ので、トランザクションで包まず、触る行は tearDown で消す。
 */
class ShardSeedersTest extends TestCase
{
    use RefreshMultipleDatabases;

    /** 3 つ目のシャードに割り当てるプレイヤー（他のテストの ID と衝突しない値） */
    private const PLAYER_ID = 9301;

    public function beginDatabaseTransaction(): void
    {
        // シーダーの truncate が暗黙コミットになるため、自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUp();
        $this->enableThreeShards();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        DB::purge('trx3');

        parent::tearDown();
    }

    #[Test]
    public function 既定は2シャードで_3シャードにすると全シーダーが3本の接続を走査する(): void
    {
        // 期待: 3 シャードの設定では 4 つのシーダーとも trx1〜trx3 を使う（ハードコードの 2 本ではない）
        foreach ([TrxUnitSeeder::class, TrxItemSeeder::class, TrxPlayerSeeder::class, LogUnitSeeder::class] as $class) {
            $this->assertSame(['trx1', 'trx2', 'trx3'], $this->shardConnectionsOf($class), $class);
        }

        // 操作: 2 シャードの設定に戻す
        config(['database.pitr.active_trx_connections' => ['trx1', 'trx2']]);

        // 期待: 設定に従って 2 本になる（DB_SHARD_COUNT に従う）
        foreach ([TrxUnitSeeder::class, TrxItemSeeder::class, TrxPlayerSeeder::class, LogUnitSeeder::class] as $class) {
            $this->assertSame(['trx1', 'trx2'], $this->shardConnectionsOf($class), $class);
        }
    }

    #[Test]
    public function trx_unit_seederは3つ目のシャードのプレイヤーにもユニットを入れ_2回流しても積み上がらない(): void
    {
        // 前提: ユニットのマスターと、trx3 に割り当てたプレイヤー
        $this->seed(MstUnitSeeder::class);
        $this->assignPlayerToNode3();

        // 操作: 2 回流す（接続ごとの insert を記録する）
        $inserted = $this->listenInsertConnections('trx_unit');
        $this->seed(TrxUnitSeeder::class);
        $first = DB::connection('trx3')->table('trx_unit')->where('sys_player_id', self::PLAYER_ID)->count();
        $this->seed(TrxUnitSeeder::class);
        $second = DB::connection('trx3')->table('trx_unit')->where('sys_player_id', self::PLAYER_ID)->count();

        // 期待: trx3 の接続へ書き込まれ（以前は trx1 / trx2 だけ）、ユニットが入る（1 人 3〜8 個）。2 回目は truncate されて作り直しになり、積み上がらない
        $this->assertContains('trx3', $inserted->getArrayCopy());
        $this->assertGreaterThanOrEqual(3, $first);
        $this->assertLessThanOrEqual(8, $first);
        $this->assertGreaterThanOrEqual(3, $second);
        $this->assertLessThanOrEqual(8, $second);
        $this->assertSame(1, (int) DB::connection('trx3')->table('trx_unit')->min('id'));
    }

    #[Test]
    public function log_unit_seederは3つ目のシャードのユニットを持つプレイヤーのログを作り_2回流しても増えない(): void
    {
        // 前提: ユニットは（trx3 のエイリアス先の）trx2 側の DB にあり、アクセスログ（trx 接続）にそのプレイヤーの行がある
        $this->seed(MstUnitSeeder::class);
        $mstUnitId = (string) DB::connection('mst')->table('mst_unit')->value('id');
        DB::connection('trx2')->table('trx_unit')->insert([
            'sys_player_id' => self::PLAYER_ID,
            'mst_unit_id' => $mstUnitId,
            'grade' => 1,
            'level' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        DB::connection('trx')->table('log_action_api_access')->insert([
            'unique_request_id' => 'shard3-request-1',
            'sys_player_id' => self::PLAYER_ID,
            'method' => 'POST',
            'endpoint' => '/api/quest/complete',
            'status_code' => 200,
            'system_at' => '2026-01-01 00:00:00',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        // 操作: 2 回流す（trx_unit を読んだ接続を記録する）
        $selected = $this->listenSelectConnections('trx_unit');
        $this->seed(LogUnitSeeder::class);
        $first = $this->unitLogCount();
        $this->seed(LogUnitSeeder::class);

        // 期待: trx3 の接続も走査してユニットを見つけ、ログが入る。2 回流しても同じ件数
        $this->assertContains('trx3', $selected->getArrayCopy());
        $this->assertSame(1, $first);
        $this->assertSame($first, $this->unitLogCount());
    }

    /** 3 シャードの設定にし、trx2 のエイリアスの trx3 を用意する */
    private function enableThreeShards(): void
    {
        // trx3 は trx2 と同じホスト・DB を指すエイリアス（db-shard3 が無い CI でも名前解決が要らない）
        config([
            'database.pitr.active_trx_connections' => ['trx1', 'trx2', 'trx3'],
            'database.connections.trx3' => config('database.connections.trx2'),
        ]);
        DB::purge('trx3');
    }

    /**
     * 指定テーブルへの insert を実行した接続名を記録する。
     *
     * @return \ArrayObject<int, string>
     */
    private function listenInsertConnections(string $table): \ArrayObject
    {
        return $this->listenQueries("insert into `{$table}`");
    }

    /**
     * 指定テーブルを select した接続名を記録する。
     *
     * @return \ArrayObject<int, string>
     */
    private function listenSelectConnections(string $table): \ArrayObject
    {
        return $this->listenQueries('select', "from `{$table}`");
    }

    /**
     * SQL に全部の断片を含むクエリの接続名を記録する。
     *
     * @return \ArrayObject<int, string>
     */
    private function listenQueries(string ...$fragments): \ArrayObject
    {
        /** @var \ArrayObject<int, string> $names */
        $names = new \ArrayObject;
        DB::listen(function ($query) use ($names, $fragments): void {
            foreach ($fragments as $fragment) {
                if (! str_contains($query->sql, $fragment)) {
                    return;
                }
            }
            $names->append($query->connectionName);
        });

        return $names;
    }

    /** プレイヤー 1 人を作り、sys_sharding_node の node_no=3 に割り当てる */
    private function assignPlayerToNode3(): void
    {
        $shardingId = DB::connection('sys')->table('sys_sharding')->where('name', 'trx_sharding')->value('id');
        if (! $shardingId) {
            $shardingId = DB::connection('sys')->table('sys_sharding')->insertGetId([
                'name' => 'trx_sharding',
                'target' => 'transaction',
                'strategy' => 'hash',
                'sharding_key' => 'player_id',
                'node_count' => 3,
                'is_active' => true,
                'description' => 'Test sharding',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $nodeId = DB::connection('sys')->table('sys_sharding_node')->insertGetId([
            'sys_sharding_id' => $shardingId,
            'node_name' => 'node3-seeder-test',
            'node_no' => 3,
            'weight' => 100,
            'status' => 'active',
            'is_writable' => true,
            'is_readable' => true,
            'max_connections' => 10000,
            'current_player_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('sys')->table('sys_player')->insert([
            'id' => self::PLAYER_ID,
            'uuid' => 'shard3-uuid-'.self::PLAYER_ID,
            'my_id' => 'SHARD3',
            'name' => 'Shard3 Player',
            'vip_point' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('sys')->table('sys_sharding_node_player')->insert([
            'sys_player_id' => self::PLAYER_ID,
            'sys_sharding_node_id' => $nodeId,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * シーダーが使う接続名の一覧（protected の shardConnections() を呼ぶ）。
     *
     * @param  class-string  $class
     * @return list<string>
     */
    private function shardConnectionsOf(string $class): array
    {
        $method = new ReflectionMethod($class, 'shardConnections');

        /** @var list<string> */
        return $method->invoke(new $class);
    }

    private function unitLogCount(): int
    {
        return DB::connection('trx')->table('log_action_unit_levelup')
            ->where('sys_player_id', self::PLAYER_ID)->count();
    }

    private function cleanUp(): void
    {
        DB::connection('trx')->table('log_action_unit_levelup')->truncate();
        DB::connection('trx')->table('log_action_api_access')->truncate();
        DB::connection('mst')->table('mst_unit')->delete();
        // trx3 は trx2 のエイリアスなので、trx2 側のユニットを空にする（シーダーが truncate する表）
        DB::connection('trx2')->table('trx_unit')->truncate();

        DB::connection('sys')->table('sys_sharding_node_player')->where('sys_player_id', self::PLAYER_ID)->delete();
        DB::connection('sys')->table('sys_player')->where('id', self::PLAYER_ID)->delete();
        DB::connection('sys')->table('sys_sharding_node')->where('node_name', 'node3-seeder-test')->delete();
    }
}
