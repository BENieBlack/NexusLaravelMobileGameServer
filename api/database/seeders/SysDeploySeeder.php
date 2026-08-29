<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SysDeploySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // べき等性を確保するため、既存データを削除
        DB::connection('sys')->table('sys_deploy')->truncate();
        DB::connection('sys')->table('sys_deploy_asset')->truncate();
        DB::connection('sys')->table('sys_deploy_master')->truncate();

        // シャーディング関連データも削除
        DB::connection('sys')->table('sys_sharding_node')->truncate();
        DB::connection('sys')->table('sys_sharding')->truncate();

        // マスターデータデプロイ履歴を作成
        $masterDeploys = [
            [
                'deploy_key' => 202601010,
                'hash' => hash('sha256', 'master_v1.0.0'),
                'deploy_date' => '2026-01-01',
                'deploy_count' => 1,
                'status' => 'completed',
                'deployed_by' => 'admin@example.com',
                'deployed_at' => '2026-01-01 00:00:00',
                'description' => 'Initial master data deployment',
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
            [
                'deploy_key' => 202602010,
                'hash' => hash('sha256', 'master_v1.1.0'),
                'deploy_date' => '2026-02-01',
                'deploy_count' => 1,
                'status' => 'completed',
                'deployed_by' => 'admin@example.com',
                'deployed_at' => '2026-02-01 12:00:00',
                'description' => 'Added new units and equipment',
                'created_at' => '2026-02-01 12:00:00',
                'updated_at' => '2026-02-01 12:00:00',
            ],
            [
                'deploy_key' => 202602220,
                'hash' => hash('sha256', 'master_v1.2.0'),
                'deploy_date' => '2026-02-22',
                'deploy_count' => 1,
                'status' => 'completed',
                'deployed_by' => 'admin@example.com',
                'deployed_at' => '2026-02-22 00:00:00',
                'description' => 'Current master data version',
                'created_at' => '2026-02-22 00:00:00',
                'updated_at' => '2026-02-22 00:00:00',
            ],
        ];

        $masterIds = [];
        foreach ($masterDeploys as $deploy) {
            $masterIds[] = DB::connection('sys')->table('sys_deploy_master')->insertGetId($deploy);
        }

        // アセットデプロイ履歴を作成
        $assetDeploys = [
            [
                'deploy_key' => 202601010,
                'hash' => hash('sha256', 'asset_v1.0.0'),
                'deploy_date' => '2026-01-01',
                'deploy_count' => 1,
                'status' => 'completed',
                's3_bucket' => 'game-assets-prod',
                's3_path' => 'v1.0.0/',
                'asset_version' => '1.0.0',
                'total_size' => 524288000, // 500MB
                'file_count' => 1250,
                'deployed_by' => 'admin@example.com',
                'deployed_at' => '2026-01-01 00:00:00',
                'description' => 'Initial asset deployment',
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ],
            [
                'deploy_key' => 202602010,
                'hash' => hash('sha256', 'asset_v1.1.0'),
                'deploy_date' => '2026-02-01',
                'deploy_count' => 1,
                'status' => 'completed',
                's3_bucket' => 'game-assets-prod',
                's3_path' => 'v1.1.0/',
                'asset_version' => '1.1.0',
                'total_size' => 629145600, // 600MB
                'file_count' => 1580,
                'deployed_by' => 'admin@example.com',
                'deployed_at' => '2026-02-01 12:00:00',
                'description' => 'Added new character graphics and BGM',
                'created_at' => '2026-02-01 12:00:00',
                'updated_at' => '2026-02-01 12:00:00',
            ],
            [
                'deploy_key' => 202602220,
                'hash' => hash('sha256', 'asset_v1.2.0'),
                'deploy_date' => '2026-02-22',
                'deploy_count' => 1,
                'status' => 'completed',
                's3_bucket' => 'game-assets-prod',
                's3_path' => 'v1.2.0/',
                'asset_version' => '1.2.0',
                'total_size' => 681574400, // 650MB
                'file_count' => 1720,
                'deployed_by' => 'admin@example.com',
                'deployed_at' => '2026-02-22 00:00:00',
                'description' => 'Current asset version',
                'created_at' => '2026-02-22 00:00:00',
                'updated_at' => '2026-02-22 00:00:00',
            ],
        ];

        $assetIds = [];
        foreach ($assetDeploys as $deploy) {
            $assetIds[] = DB::connection('sys')->table('sys_deploy_asset')->insertGetId($deploy);
        }

        // デプロイ統合テーブルを作成
        $deploys = [
            [
                'deploy_key' => 202601010,
                'start_at' => '2026-01-01 00:00:00',
                'sys_deploy_master_id' => $masterIds[0],
                'sys_deploy_asset_id' => $assetIds[0],
                'is_active' => false,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-02-01 12:00:00',
            ],
            [
                'deploy_key' => 202602010,
                'start_at' => '2026-02-01 12:00:00',
                'sys_deploy_master_id' => $masterIds[1],
                'sys_deploy_asset_id' => $assetIds[1],
                'is_active' => false,
                'created_at' => '2026-02-01 12:00:00',
                'updated_at' => '2026-02-22 00:00:00',
            ],
            [
                'deploy_key' => 202602220,
                'start_at' => '2026-02-22 00:00:00',
                'sys_deploy_master_id' => $masterIds[2],
                'sys_deploy_asset_id' => $assetIds[2],
                'is_active' => true, // 現在の有効バージョン
                'created_at' => '2026-02-22 00:00:00',
                'updated_at' => '2026-02-22 00:00:00',
            ],
        ];

        foreach ($deploys as $deploy) {
            DB::connection('sys')->table('sys_deploy')->insert($deploy);
        }

        // シャーディング設定を作成
        $shardingId = DB::connection('sys')->table('sys_sharding')->insertGetId([
            'name' => 'trx_sharding',
            'target' => 'transaction',
            'strategy' => 'hash',
            'sharding_key' => 'player_id',
            'node_count' => (int) env('DB_SHARD_COUNT', 3),
            'is_active' => true,
            'description' => 'Transaction data sharding',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // シャーディングノードを作成
        $shardCount = (int) env('DB_SHARD_COUNT', 3);
        for ($i = 1; $i <= $shardCount; $i++) {
            DB::connection('sys')->table('sys_sharding_node')->insert([
                'sys_sharding_id' => $shardingId,
                'node_name' => "node{$i}",
                'node_no' => $i,
                'weight' => 100,
                'status' => 'active',
                'is_writable' => true,
                'is_readable' => true,
                'max_connections' => 10000,
                'current_player_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ SysDeploySeeder: Created 3 master deploys, 3 asset deploys, 3 unified deploys, and '.$shardCount.' sharding nodes');
    }
}
