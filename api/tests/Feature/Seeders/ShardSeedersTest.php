<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\LogUnitSeeder;
use Database\Seeders\MstUnitSeeder;
use Database\Seeders\TrxItemSeeder;
use Database\Seeders\TrxPlayerSeeder;
use Database\Seeders\TrxUnitSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
 * テストの環境は 2 シャード固定（phpunit.xml）なので、config を差し替えて 3 シャードにする。
 * trx3 の接続は、phpunit.xml に残してある shard3 の接続情報（db-shard3）で作り、
 * 必要なテーブル（trx_unit）はこのテストの中で作って、終わりに消す。
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
        Schema::connection('trx3')->dropIfExists('trx_unit');

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

        // 操作: 2 回流す
        $this->seed(TrxUnitSeeder::class);
        $first = DB::connection('trx3')->table('trx_unit')->where('sys_player_id', self::PLAYER_ID)->count();
        $this->seed(TrxUnitSeeder::class);
        $second = DB::connection('trx3')->table('trx_unit')->where('sys_player_id', self::PLAYER_ID)->count();

        // 期待: trx3 にユニットが入る（1 人 3〜8 個）。2 回目は truncate されて作り直しになり、積み上がらない
        $this->assertGreaterThanOrEqual(3, $first);
        $this->assertLessThanOrEqual(8, $first);
        $this->assertGreaterThanOrEqual(3, $second);
        $this->assertLessThanOrEqual(8, $second);
        $this->assertSame(1, (int) DB::connection('trx3')->table('trx_unit')->min('id'));
    }

    #[Test]
    public function log_unit_seederは3つ目のシャードのユニットを持つプレイヤーのログを作り_2回流しても増えない(): void
    {
        // 前提: ユニットは trx3 だけにあり、アクセスログ（trx 接続）にそのプレイヤーの行がある
        $this->seed(MstUnitSeeder::class);
        $mstUnitId = (string) DB::connection('mst')->table('mst_unit')->value('id');
        DB::connection('trx3')->table('trx_unit')->insert([
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

        // 操作: 2 回流す
        $this->seed(LogUnitSeeder::class);
        $first = $this->unitLogCount();
        $this->seed(LogUnitSeeder::class);

        // 期待: trx3 のユニットを見つけてログが入る（以前は「No units found」で 0 件）。2 回流しても同じ件数
        $this->assertSame(1, $first);
        $this->assertSame($first, $this->unitLogCount());
    }

    /** 3 シャードの設定にし、trx3 の接続と、シーダーが触るテーブルを用意する */
    private function enableThreeShards(): void
    {
        config([
            'database.pitr.active_trx_connections' => ['trx1', 'trx2', 'trx3'],
            'database.connections.trx3' => array_merge((array) config('database.connections.trx2'), [
                'host' => env('DB_SHARD3_HOST', 'db-shard3'),
                'port' => env('DB_SHARD3_PORT', '3306'),
                'database' => env('DB_SHARD3_DATABASE', 'nexus-testing-shard'),
                'username' => env('DB_SHARD3_USERNAME', 'root'),
                'password' => env('DB_SHARD3_PASSWORD', 'root'),
            ]),
        ]);
        DB::purge('trx3');

        Schema::connection('trx3')->dropIfExists('trx_unit');
        Schema::connection('trx3')->create('trx_unit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_player_id');
            $table->string('mst_unit_id');
            $table->unsignedInteger('grade');
            $table->unsignedInteger('level');
            $table->unsignedBigInteger('level_exp')->default(0);
            $table->boolean('is_delete')->default(false);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
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

        DB::connection('sys')->table('sys_sharding_node_player')->where('sys_player_id', self::PLAYER_ID)->delete();
        DB::connection('sys')->table('sys_player')->where('id', self::PLAYER_ID)->delete();
        DB::connection('sys')->table('sys_sharding_node')->where('node_name', 'node3-seeder-test')->delete();
    }
}
