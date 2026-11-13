<?php

namespace Database\Seeders;

use App\Models\Sys\SysSharding;
use App\Models\Sys\SysShardingNode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\_BaseModel;

/**
 * SysShardingSeeder
 *
 * トランザクション DB の割り当て先（sys_sharding / sys_sharding_node）を 2 ノードで作る。
 * ローカルは DB_SHARD_COUNT=3（trx1〜trx3）だが、基本のシードでは意図して 2 ノードのままにしている:
 * - CI は 2 シャード固定（db-shard1 / db-shard2）で、ここを 3 にすると CI で割り当てた先が存在しない
 * - 新規ユーザーの割り当ては 2 ノードあれば足りる
 * - trx3 は trx2 の別名として扱う（シーダー・テストの接続一覧は DB_SHARD_COUNT から作る）
 * 3 ノードで動作を確かめたいときは、検証用に node_count とノードを足す（基本のシードは変えない）。
 */
class SysShardingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seederは投入経路のためUnitOfWorkを介さない直接書き込みを許可する
        _BaseModel::allowDirectWrites();

        // 既存データをクリア
        DB::connection('sys')->table('sys_sharding_node')->truncate();
        DB::connection('sys')->table('sys_sharding')->truncate();

        // シャーディング設定を作成
        $sharding = SysSharding::create([
            'name' => 'trx_sharding',
            'target' => SysSharding::TARGET_TRANSACTION,
            'strategy' => SysSharding::STRATEGY_HASH,
            'sharding_key' => 'player_id',
            'node_count' => 2,
            'is_active' => true,
            'description' => 'トランザクションDBの2ノード構成シャーディング',
        ]);

        // ノード1を作成
        $node1 = new SysShardingNode([
            'sys_sharding_id' => $sharding->id,
            'node_name' => 'node1',
            'node_no' => 1,
            'weight' => 100,
            'status' => SysShardingNode::STATUS_ACTIVE,
            'is_writable' => true,
            'is_readable' => true,
            'max_connections' => 10000,
            'current_player_count' => 0,
        ]);
        $node1->setConnection('sys');
        $node1->save();

        // ノード2を作成
        $node2 = new SysShardingNode([
            'sys_sharding_id' => $sharding->id,
            'node_name' => 'node2',
            'node_no' => 2,
            'weight' => 100,
            'status' => SysShardingNode::STATUS_ACTIVE,
            'is_writable' => true,
            'is_readable' => true,
            'max_connections' => 10000,
            'current_player_count' => 0,
        ]);
        $node2->setConnection('sys');
        $node2->save();

        $this->command->info('シャーディング設定とノード情報を作成しました。');
        $this->command->info("シャーディングID: {$sharding->id}");
        $this->command->info("ノード数: {$sharding->node_count}");
    }
}
