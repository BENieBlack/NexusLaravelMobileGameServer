<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * GET /auth/version の master.tables が、登録した全テーブルをテーブル名キーで返すことの回帰テスト
 */
class VersionMasterTablesTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected function setUp(): void
    {
        parent::setUp();

        // 他のデプロイが最新扱いにならないよう、既存のデプロイ情報を空にする
        $this->clearDeployData();
    }

    protected function tearDown(): void
    {
        $this->clearDeployData();

        parent::tearDown();
    }

    private function clearDeployData(): void
    {
        foreach (['sys_deploy', 'sys_deploy_master_table', 'sys_deploy_master', 'sys_deploy_asset'] as $table) {
            DB::connection('sys')->table($table)->delete();
        }
    }

    public function test_master_tables_は登録した全テーブルをテーブル名のキーで返す(): void
    {
        $masterId = DB::connection('sys')->table('sys_deploy_master')->insertGetId([
            'deploy_key' => 202601010,
            'hash' => hash('sha256', 'master'),
            'deploy_date' => '2026-01-01',
            'deploy_count' => 1,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $assetId = DB::connection('sys')->table('sys_deploy_asset')->insertGetId([
            'deploy_key' => 202601010,
            'hash' => hash('sha256', 'asset'),
            'deploy_date' => '2026-01-01',
            'deploy_count' => 1,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('sys')->table('sys_deploy')->insert([
            'deploy_key' => 202601010,
            'start_at' => now()->subMinute(),
            'sys_deploy_master_id' => $masterId,
            'sys_deploy_asset_id' => $assetId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $names = ['mst_item', 'mst_unit', 'mst_gacha'];
        foreach ($names as $i => $name) {
            DB::connection('sys')->table('sys_deploy_master_table')->insert([
                'sys_deploy_master_id' => $masterId,
                'table_name' => $name,
                'hash' => "hash-{$name}",
                'file_size' => 1000 + $i,
                'file_name' => "{$name}.sqlite",
                'public_url' => "/masterdata/{$name}.sqlite",
            ]);
        }

        $response = $this->getJson('/api/auth/version');

        $response->assertOk();
        $this->assertTrue($response->json('needs_update'));
        $tables = $response->json('master.tables');
        $this->assertCount(3, $tables);
        foreach ($names as $i => $name) {
            $this->assertSame([
                'hash' => "hash-{$name}",
                'url' => "/masterdata/{$name}.sqlite",
                'file_size' => 1000 + $i,
            ], $tables[$name]);
        }
    }
}
