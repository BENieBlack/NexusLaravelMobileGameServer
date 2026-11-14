<?php

namespace Tests\Feature\Domain\Stamina\Services;

use App\Domain\MstConfig\Constants\MstConfigKey;
use App\Domain\Stamina\Services\StaminaRecoveryIntervalAdapter;
use App\Repositories\Mst\MstConfigRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NexusStamina\Services\StaminaRecoveryIntervalProviderInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * StaminaRecoveryIntervalAdapter のテスト
 */
class StaminaRecoveryIntervalAdapterTest extends TestCase
{
    use RefreshMultipleDatabases;

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
    public function パッケージの読み出し口はアダプタに束縛され_リクエスト内で共有される(): void
    {
        $first = app(StaminaRecoveryIntervalProviderInterface::class);

        $this->assertInstanceOf(StaminaRecoveryIntervalAdapter::class, $first);
        $this->assertSame($first, app(StaminaRecoveryIntervalProviderInterface::class));
    }

    #[Test]
    public function mst_configの行の値を返す(): void
    {
        $this->insertInterval('600');

        $this->assertSame(600, app(StaminaRecoveryIntervalProviderInterface::class)->findRecoveryIntervalSeconds());
    }

    #[Test]
    public function 行が無ければ既定値を返す(): void
    {
        $this->assertSame(
            MstConfigKey::StaminaRecoveryIntervalSeconds->defaultInt(),
            app(StaminaRecoveryIntervalProviderInterface::class)->findRecoveryIntervalSeconds()
        );
    }

    #[Test]
    public function 同じリクエスト内では値を保持し_警告ログは1回だけ(): void
    {
        Log::spy();
        $adapter = app(StaminaRecoveryIntervalProviderInterface::class);

        // 行が無い状態で複数回呼んでも警告は 1 回
        $adapter->findRecoveryIntervalSeconds();
        $adapter->findRecoveryIntervalSeconds();
        Log::shouldHaveReceived('warning')->once();

        // 同じリクエスト内で行が入っても、最初の値を返し続ける
        $this->insertInterval('600');
        $this->assertSame(300, $adapter->findRecoveryIntervalSeconds());
    }

    #[Test]
    public function 次のリクエストでは読み直す(): void
    {
        $this->insertInterval('600');
        $this->assertSame(600, app(StaminaRecoveryIntervalProviderInterface::class)->findRecoveryIntervalSeconds());

        DB::connection('mst')->table('mst_config')->update(['value' => '900']);
        app(MstConfigRepository::class)->clearCache();
        // scoped インスタンスの破棄は、リクエストの終わりに相当する
        $this->app->forgetScopedInstances();

        $this->assertSame(900, app(StaminaRecoveryIntervalProviderInterface::class)->findRecoveryIntervalSeconds());
    }

    private function insertInterval(string $value): void
    {
        DB::connection('mst')->table('mst_config')->insert([
            'deploy_key' => 202601010,
            'id' => MstConfigKey::StaminaRecoveryIntervalSeconds->value,
            'value' => $value,
            'value_type' => 'int',
            'description' => 'テスト用',
        ]);
        app(MstConfigRepository::class)->clearCache();
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_config')->delete();
        app(MstConfigRepository::class)->clearCache();
    }
}
