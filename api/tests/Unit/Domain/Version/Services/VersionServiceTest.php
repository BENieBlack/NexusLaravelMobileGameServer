<?php

namespace Tests\Unit\Domain\Version\Services;

use App\Domain\Version\Services\VersionService;
use App\Exceptions\SystemDataException;
use App\Models\Sys\SysDeploy;
use App\Models\Sys\SysDeployAsset;
use App\Models\Sys\SysDeployMaster;
use App\Models\Sys\SysMaintenance;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class VersionServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private VersionService $service;

    /**
     * Define database connections to migrate for this test
     */
    protected function connectionsToMigrate(): array
    {
        return [
            'mst' => 'database/migrations/mst',
            'sys' => 'database/migrations/sys',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Redisキャッシュをクリア
        Redis::flushdb();

        // DIコンテナからServiceを取得
        $this->service = app(VersionService::class);

        // Suppress log output during tests
        Log::spy();
    }

    /**
     * Test checkVersion throws exception when no downloadable deploy exists
     */
    public function test_check_version_throws_exception_when_no_deploy_exists(): void
    {
        // キャッシュをクリア
        Cache::store('redis')->clear();

        // すべてのデプロイデータを削除
        SysDeploy::query()->delete();
        SysDeployAsset::query()->delete();
        SysDeployMaster::query()->delete();

        // Assert & Act
        $this->expectException(SystemDataException::class);
        $this->service->checkVersion(null);
    }

    /**
     * Test checkVersion returns sysDeploy null when client has latest version
     */
    public function test_check_version_returns_no_update_when_client_is_latest(): void
    {
        // キャッシュをクリア
        Cache::store('redis')->clear();

        // すべてのデプロイデータを削除
        SysDeploy::query()->delete();
        SysDeployAsset::query()->delete();
        SysDeployMaster::query()->delete();

        // Arrange
        $master = SysDeployMaster::create([
            'deploy_key' => 202601010,
            'hash' => 'master_hash_001',
            'deploy_date' => now()->subDay()->toDateString(),
            'deploy_count' => 0,
            'deployed_at' => now()->subDay(),
        ]);

        $asset = SysDeployAsset::create([
            'deploy_key' => 202601010,
            'hash' => 'asset_hash_001',
            'deploy_date' => now()->subDay()->toDateString(),
            'deploy_count' => 0,
            'deployed_at' => now()->subDay(),
        ]);

        $deploy = SysDeploy::create([
            'deploy_key' => 202601010,
            'start_at' => now()->subHour(),
            'sys_deploy_master_id' => $master->id,
            'sys_deploy_asset_id' => $asset->id,
            'is_active' => true,
        ]);

        // Act
        [$sysDeploy, $sysMaintenance] = $this->service->checkVersion($deploy->id);

        // Assert
        $this->assertNull($sysDeploy);
    }

    /**
     * Test checkVersion returns update available when client is outdated
     */
    public function test_check_version_returns_update_available_when_client_is_outdated(): void
    {
        // キャッシュをクリア
        Cache::store('redis')->clear();

        // すべてのデプロイデータを削除
        SysDeploy::query()->delete();
        SysDeployAsset::query()->delete();
        SysDeployMaster::query()->delete();

        // Arrange - Create old deploy
        $oldMaster = SysDeployMaster::create([
            'hash' => 'master_hash_001',
            'deploy_key' => 202601010,
            'deploy_date' => now()->subDays(2)->toDateString(),
            'deploy_count' => 0,
            'deployed_at' => now()->subDays(2),
        ]);

        $oldAsset = SysDeployAsset::create([
            'hash' => 'asset_hash_001',
            'deploy_key' => 202601010,
            'deploy_date' => now()->subDays(2)->toDateString(),
            'deploy_count' => 0,
            'deployed_at' => now()->subDays(2),
        ]);

        $oldDeploy = SysDeploy::create([
            'deploy_key' => 202601010,
            'start_at' => now()->subDays(2),
            'sys_deploy_master_id' => $oldMaster->id,
            'sys_deploy_asset_id' => $oldAsset->id,
            'is_active' => true,
        ]);

        // Arrange - Create new deploy
        $newMaster = SysDeployMaster::create([
            'deploy_key' => 202601020,
            'hash' => 'master_hash_002',
            'deploy_date' => now()->subDay()->toDateString(),
            'deploy_count' => 0,
            'deployed_at' => now()->subDay(),
        ]);

        $newAsset = SysDeployAsset::create([
            'deploy_key' => 202601020,
            'hash' => 'asset_hash_002',
            'deploy_date' => now()->subDay()->toDateString(),
            'deploy_count' => 0,
            'deployed_at' => now()->subDay(),
        ]);

        $newDeploy = SysDeploy::create([
            'deploy_key' => 202601020,
            'start_at' => now()->subHour(),
            'sys_deploy_master_id' => $newMaster->id,
            'sys_deploy_asset_id' => $newAsset->id,
            'is_active' => true,
        ]);

        // Act
        [$sysDeploy, $sysMaintenance] = $this->service->checkVersion($oldDeploy->id);

        // Assert
        $this->assertNotNull($sysDeploy);
        $this->assertEquals($newDeploy->id, $sysDeploy->id);
        $this->assertEquals($newDeploy->deploy_key, $sysDeploy->deploy_key);
        $this->assertNotNull($sysDeploy->deployMaster);
        $this->assertNotNull($sysDeploy->deployAsset);
        $this->assertEquals($newMaster->hash, $sysDeploy->deployMaster->hash);
        $this->assertEquals($newAsset->hash, $sysDeploy->deployAsset->hash);
    }

    /**
     * Test checkVersion includes maintenance info when maintenance is active
     */
    public function test_check_version_includes_maintenance_info_when_active(): void
    {
        // キャッシュをクリア
        Cache::store('redis')->clear();

        // すべてのデプロイデータを削除
        SysDeploy::query()->delete();
        SysDeployAsset::query()->delete();
        SysDeployMaster::query()->delete();
        SysMaintenance::query()->delete();

        // Arrange - Create maintenance
        $maintenance = SysMaintenance::create([
            'title' => 'Scheduled Maintenance',
            'message' => 'System will be under maintenance',
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);

        // Arrange - Create deploy
        $master = SysDeployMaster::create([
            'deploy_key' => 202601010,
            'hash' => 'master_hash_001',
            'deploy_date' => now()->subDay()->toDateString(),
            'deploy_count' => 0,
            'deployed_at' => now()->subDay(),
        ]);

        $asset = SysDeployAsset::create([
            'deploy_key' => 202601010,
            'hash' => 'asset_hash_001',
            'deploy_date' => now()->subDay()->toDateString(),
            'deploy_count' => 0,
            'deployed_at' => now()->subDay(),
        ]);

        $deploy = SysDeploy::create([
            'deploy_key' => 202601010,
            'start_at' => now()->subHour(),
            'sys_deploy_master_id' => $master->id,
            'sys_deploy_asset_id' => $asset->id,
            'is_active' => true,
        ]);

        // Act
        [$sysDeploy, $sysMaintenance] = $this->service->checkVersion($deploy->id);

        // Assert
        $this->assertNotNull($sysMaintenance);
        $this->assertEquals('Scheduled Maintenance', $sysMaintenance->title);
        $this->assertEquals('System will be under maintenance', $sysMaintenance->message);
    }
}
