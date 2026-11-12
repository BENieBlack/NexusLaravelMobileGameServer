<?php

namespace Tests\Feature\Recording;

use Database\Seeders\Recording\RecordingScenarioSeeder;
use Database\Seeders\Recording\RecordingSeeder;
use Database\Seeders\Recording\Scenarios\RecordingAgreementSeeder;
use Database\Seeders\Recording\Scenarios\RecordingDeploySeeder;
use Database\Seeders\Recording\Scenarios\RecordingLoginBonusDisabledSeeder;
use Database\Seeders\Recording\Scenarios\RecordingLoginBonusSeeder;
use Database\Seeders\Recording\Scenarios\RecordingMaintenanceOffSeeder;
use Database\Seeders\Recording\Scenarios\RecordingMaintenanceOnSeeder;
use Database\Seeders\Recording\Scenarios\RecordingResetSeeder;
use Illuminate\Support\Facades\DB;
use NexusMaintenance\Contracts\MaintenanceStorageInterface;
use NexusMaintenance\Infrastructure\Database\DatabaseMaintenanceStorage;
use NexusMaintenance\Services\MaintenanceService;
use PHPUnit\Framework\Attributes\Test;

/**
 * 動画撮影用シーダー全般（環境ガード・冪等性・マーカー・メンテナンス・リセット）のテスト。
 * 規約の API 経由の確認は RecordingAgreementPendingTest。
 */
class RecordingScenarioTest extends RecordingTestCase
{
    #[Test]
    public function 許可されていない環境では例外で止まり行は残る(): void
    {
        // 前提: マーカー行を投入済み（local として流す）
        $this->runSeeder(RecordingAgreementSeeder::class);
        $this->runSeeder(RecordingMaintenanceOnSeeder::class);

        // 本番相当の環境に切り替える
        $this->app->instance('env', 'production');

        // 操作 → 期待: どのシナリオも例外になり、行は消えも変わりもしない
        $seeders = [
            RecordingSeeder::class, RecordingAgreementSeeder::class, RecordingLoginBonusSeeder::class,
            RecordingMaintenanceOnSeeder::class, RecordingMaintenanceOffSeeder::class, RecordingResetSeeder::class,
        ];
        foreach ($seeders as $seeder) {
            try {
                $this->app->make($seeder)->run();
                $this->fail("{$seeder} が本番相当の環境で動いた");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('production', $e->getMessage());
            }
        }
        $this->assertSame(3, $this->countAgreements(markedOnly: true));
        $this->assertSame(1, $this->countMaintenance(markedOnly: true));
        $this->assertSame(1, DB::connection('sys')->table('sys_maintenance')->where('title', 'like', '[REC]%')->where('is_active', true)->count());
    }

    #[Test]
    public function メンテナンスはキャッシュが有効でも有効化と解除が即時に反映される(): void
    {
        // 前提: 実際のストレージ（DB）とキャッシュ有効の MaintenanceService
        $this->app->singleton(MaintenanceStorageInterface::class, fn () => new DatabaseMaintenanceStorage);
        $this->app->forgetInstance(MaintenanceService::class);
        config(['maintenance.cache.enabled' => true]);
        $service = $this->app->make(MaintenanceService::class);
        DB::connection('sys')->table('sys_maintenance')->update(['is_active' => false]);
        $this->assertFalse($service->isUnderMaintenance());

        // On → 503 になる（この判定でキャッシュに載る）
        $this->runSeeder(RecordingMaintenanceOnSeeder::class);
        $this->assertTrue($service->isUnderMaintenance());

        // Off の直後に解ける（キャッシュを消していなければ TTL の間 true のまま）
        $this->runSeeder(RecordingMaintenanceOffSeeder::class);
        $this->assertFalse($service->isUnderMaintenance());

        // Reset も同様
        $this->runSeeder(RecordingMaintenanceOnSeeder::class);
        $this->assertTrue($service->isUnderMaintenance());
        $this->runSeeder(RecordingResetSeeder::class);
        $this->assertFalse($service->isUnderMaintenance());
    }

    #[Test]
    public function 規約は3件作られ2回流しても増えない(): void
    {
        $before = $this->countAgreements(markedOnly: false);

        $this->runSeeder(RecordingAgreementSeeder::class);
        $this->runSeeder(RecordingAgreementSeeder::class);

        $this->assertSame(3, $this->countAgreements(markedOnly: true));
        $this->assertSame($before + 3, $this->countAgreements(markedOnly: false));
        $this->assertSame(
            ['rec-future', 'rec-optional', 'rec-required'],
            DB::connection('sys')->table('sys_agreement')->where('version', 'like', 'rec-%')->orderBy('version')->pluck('version')->all(),
        );
    }

    #[Test]
    public function ログインボーナスは作られ無効から有効に戻り行数は変わらない(): void
    {
        $this->runSeeder(RecordingLoginBonusSeeder::class);
        $bonusCount = DB::connection('mst')->table('mst_login_bonus')->count();
        $contentCount = DB::connection('mst')->table('mst_login_bonus_content')->count();

        // 2回目: 行数は変わらない
        $this->runSeeder(RecordingLoginBonusSeeder::class);
        $this->assertSame($bonusCount, DB::connection('mst')->table('mst_login_bonus')->count());
        $this->assertSame($contentCount, DB::connection('mst')->table('mst_login_bonus_content')->count());
        $this->assertSame(7, DB::connection('mst')->table('mst_login_bonus_content')->where('mst_login_bonus_id', 'daily_login')->distinct()->count('day'));

        // 無効化 → 有効に戻す
        $this->runSeeder(RecordingLoginBonusDisabledSeeder::class);
        $this->assertFalse($this->dailyLoginIsActive());
        $this->runSeeder(RecordingLoginBonusSeeder::class);
        $this->assertTrue($this->dailyLoginIsActive());
        $this->assertSame($bonusCount, DB::connection('mst')->table('mst_login_bonus')->count());
    }

    #[Test]
    public function ログインボーナスは他の行を消さない(): void
    {
        // 前提: 別のログインボーナスが1件ある
        DB::connection('mst')->table('mst_login_bonus')->updateOrInsert(['id' => 'other_bonus'], [
            'type' => 'daily', 'loop_days' => 7, 'priority' => 0, 'is_active' => true,
        ]);

        $this->runSeeder(RecordingLoginBonusSeeder::class);
        $this->runSeeder(RecordingLoginBonusDisabledSeeder::class);
        $this->runSeeder(RecordingResetSeeder::class);

        $this->assertTrue(DB::connection('mst')->table('mst_login_bonus')->where('id', 'other_bonus')->exists());
    }

    #[Test]
    public function メンテナンスを有効にして解除できマーカー行だけを触る(): void
    {
        // 前提: 運営が入れた本物の（マーカーなし）メンテナンスが1件ある
        DB::connection('sys')->table('sys_maintenance')->insert([
            'title' => '本物のメンテナンス', 'message' => 'm', 'start_at' => '2020-01-01 00:00:00', 'end_at' => null, 'is_active' => false,
        ]);
        $before = $this->countMaintenance(markedOnly: false);

        // 有効化（2回流しても1行）
        $this->runSeeder(RecordingMaintenanceOnSeeder::class);
        $this->runSeeder(RecordingMaintenanceOnSeeder::class);
        $this->assertSame($before + 1, $this->countMaintenance(markedOnly: false));
        $this->assertSame(1, $this->countMaintenance(markedOnly: true));

        // 実際に使われるストレージが、動画用のメンテナンスを現在のメンテナンスとして返す
        $current = (new DatabaseMaintenanceStorage)->get();
        $this->assertNotNull($current);
        $this->assertTrue($current->isCurrentlyUnderMaintenance());
        $this->assertStringStartsWith('[REC]', $current->getTitle());

        // 解除: マーカー行だけ無効になり、本物の行は変わらない
        $this->runSeeder(RecordingMaintenanceOffSeeder::class);
        $this->assertSame(
            0,
            DB::connection('sys')->table('sys_maintenance')->where('title', 'like', '[REC]%')->where('is_active', true)->count(),
        );
        $this->assertSame($before + 1, $this->countMaintenance(markedOnly: false));
        $this->assertFalse((bool) DB::connection('sys')->table('sys_maintenance')->where('title', '本物のメンテナンス')->value('is_active'));
    }

    #[Test]
    public function デプロイは有効な本物があれば追加せず無ければ追加して2回流しても増えない(): void
    {
        // 前提: 有効なデプロイを無くす（トランザクションで巻き戻る）
        DB::connection('sys')->table('sys_deploy')->update(['is_active' => false]);

        $this->runSeeder(RecordingDeploySeeder::class);
        $this->runSeeder(RecordingDeploySeeder::class);

        $this->assertSame(1, DB::connection('sys')->table('sys_deploy')->where('deploy_key', RecordingDeploySeeder::DEPLOY_KEY)->count());
        $this->assertSame(1, DB::connection('sys')->table('sys_deploy_master')->where('description', 'like', '[REC]%')->count());
        $this->assertSame(1, DB::connection('sys')->table('sys_deploy_asset')->where('description', 'like', '[REC]%')->count());

        // version API が成功する（デプロイが1件も無いと 299 になる）
        $this->getJson('/api/auth/version')->assertOk()->assertJsonPath('needs_update', true);
    }

    #[Test]
    public function 有効な本物のデプロイがあればデプロイを追加しない(): void
    {
        DB::connection('sys')->table('sys_deploy')->where('deploy_key', RecordingDeploySeeder::DEPLOY_KEY)->delete();
        DB::connection('sys')->table('sys_deploy')->update(['is_active' => false]);
        DB::connection('sys')->table('sys_deploy')->insert([
            'deploy_key' => 190001011, 'start_at' => '2020-01-01 00:00:00',
            'sys_deploy_master_id' => 1, 'sys_deploy_asset_id' => 1, 'is_active' => true,
        ]);
        $before = DB::connection('sys')->table('sys_deploy')->count();

        $this->runSeeder(RecordingDeploySeeder::class);

        $this->assertSame($before, DB::connection('sys')->table('sys_deploy')->count());
    }

    #[Test]
    public function リセットはマーカー行だけを消し非マーカー行を残す(): void
    {
        // 前提: 非マーカーの規約・メンテナンス・デプロイと、全シナリオの投入
        DB::connection('sys')->table('sys_agreement')->insert([
            'version' => '1.0.0', 'title' => '本物の規約', 'url' => 'https://example.com', 'is_required' => true, 'published_at' => '2020-01-01 00:00:00',
        ]);
        DB::connection('sys')->table('sys_maintenance')->insert([
            'title' => '本物のメンテナンス', 'message' => 'm', 'start_at' => '2020-01-01 00:00:00', 'end_at' => null, 'is_active' => false,
        ]);
        DB::connection('sys')->table('sys_deploy')->update(['is_active' => false]);
        $this->runSeeder(RecordingSeeder::class);
        $this->runSeeder(RecordingMaintenanceOnSeeder::class);
        $this->runSeeder(RecordingLoginBonusDisabledSeeder::class);
        $realAgreements = DB::connection('sys')->table('sys_agreement')->where('version', 'not like', 'rec-%')->count();
        $realMaintenance = DB::connection('sys')->table('sys_maintenance')->where('title', 'not like', '[REC]%')->count();
        $realDeploys = DB::connection('sys')->table('sys_deploy')->where('deploy_key', '!=', RecordingDeploySeeder::DEPLOY_KEY)->count();

        // 操作（2回流しても同じ）
        $this->runSeeder(RecordingResetSeeder::class);
        $this->runSeeder(RecordingResetSeeder::class);

        // 期待: マーカー行は無くなり、非マーカー行は残り、daily_login は有効
        $this->assertSame(0, $this->countAgreements(markedOnly: true));
        $this->assertSame(0, $this->countMaintenance(markedOnly: true));
        $this->assertSame(0, DB::connection('sys')->table('sys_deploy')->where('deploy_key', RecordingDeploySeeder::DEPLOY_KEY)->count());
        $this->assertSame(0, DB::connection('sys')->table('sys_deploy_master')->where('description', 'like', '[REC]%')->count());
        $this->assertSame(0, DB::connection('sys')->table('sys_deploy_asset')->where('description', 'like', '[REC]%')->count());
        $this->assertSame($realAgreements, DB::connection('sys')->table('sys_agreement')->count());
        $this->assertSame($realMaintenance, $this->countMaintenance(markedOnly: false));
        $this->assertSame($realDeploys, DB::connection('sys')->table('sys_deploy')->count());
        $this->assertTrue($this->dailyLoginIsActive());
    }

    #[Test]
    public function 既定の一覧は破壊的なシナリオを含まない(): void
    {
        $default = RecordingSeeder::defaultScenarios();

        foreach ($default as $class) {
            $this->assertTrue(is_subclass_of($class, RecordingScenarioSeeder::class));
        }
        foreach ([RecordingMaintenanceOnSeeder::class, RecordingMaintenanceOffSeeder::class, RecordingLoginBonusDisabledSeeder::class, RecordingResetSeeder::class] as $excluded) {
            $this->assertNotContains($excluded, $default);
        }
    }

    private function dailyLoginIsActive(): bool
    {
        return (bool) DB::connection('mst')->table('mst_login_bonus')->where('id', 'daily_login')->value('is_active');
    }
}
