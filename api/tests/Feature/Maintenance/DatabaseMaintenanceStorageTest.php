<?php

namespace Tests\Feature\Maintenance;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusMaintenance\Contracts\MaintenanceStorageInterface;
use NexusMaintenance\Infrastructure\Database\DatabaseMaintenanceStorage;
use NexusMaintenance\MaintenanceServiceProvider;
use NexusMaintenance\ValueObjects\Maintenance;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * DatabaseMaintenanceStorage の検証
 *
 * MAINTENANCE_DRIVER=database で実際に使われるドライバ。
 * 他のテストはTestCaseがメモリ実装に差し替えるため、このクラスだけは
 * sys_maintenance テーブルに対して直接動かして確認する。
 */
class DatabaseMaintenanceStorageTest extends TestCase
{
    use RefreshMultipleDatabases;

    private DatabaseMaintenanceStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = new DatabaseMaintenanceStorage;
        $this->table()->delete();
    }

    #[Test]
    public function 有効なレコードがなければnullを返す(): void
    {
        $this->assertNull($this->storage->get());
    }

    #[Test]
    public function 保存したメンテナンス情報を読み戻せる(): void
    {
        $this->assertTrue($this->storage->put(new Maintenance(
            isMaintenance: true,
            startAt: '2026-03-15 02:00:00',
            endAt: '2026-03-15 06:00:00',
            title: '定期メンテナンス',
            message: 'サーバ更新のため停止します',
        )));

        $maintenance = $this->storage->get();

        $this->assertNotNull($maintenance);
        $this->assertTrue($maintenance->getIsMaintenance());
        $this->assertSame('2026-03-15 02:00:00', $maintenance->getStartAt());
        $this->assertSame('2026-03-15 06:00:00', $maintenance->getEndAt());
        $this->assertSame('定期メンテナンス', $maintenance->getTitle());
        $this->assertSame('サーバ更新のため停止します', $maintenance->getMessage());
        $this->assertNotNull($maintenance->getUpdatedAt());
    }

    #[Test]
    public function 開始日時が未指定なら現在時刻で保存する(): void
    {
        ClockUtility::setNow('2026-03-15 12:34:56');

        $this->storage->put(new Maintenance(isMaintenance: true, title: '緊急メンテ', message: '調査中'));

        $this->assertSame('2026-03-15 12:34:56', $this->storage->get()?->getStartAt());
    }

    #[Test]
    public function 終了日時は未指定のままnullで保存できる(): void
    {
        $this->storage->put(new Maintenance(
            isMaintenance: true,
            startAt: '2026-03-15 02:00:00',
            title: '緊急メンテ',
            message: '終了時刻未定',
        ));

        $this->assertNull($this->storage->get()?->getEndAt());
    }

    #[Test]
    public function 保存すると以前の有効なレコードは無効化される(): void
    {
        $this->storage->put(new Maintenance(
            isMaintenance: true,
            startAt: '2026-03-14 02:00:00',
            title: '古いメンテ',
            message: '旧',
        ));
        $this->storage->put(new Maintenance(
            isMaintenance: true,
            startAt: '2026-03-15 02:00:00',
            title: '新しいメンテ',
            message: '新',
        ));

        // 履歴は残しつつ、有効なのは最後に保存した1件だけ
        $this->assertSame(2, $this->table()->count());
        $this->assertSame(1, $this->table()->where('is_active', true)->count());
        $this->assertSame('新しいメンテ', $this->storage->get()?->getTitle());
    }

    #[Test]
    public function 削除は物理削除ではなく無効化する(): void
    {
        $this->storage->put(new Maintenance(
            isMaintenance: true,
            startAt: '2026-03-15 02:00:00',
            title: 'メンテ',
            message: '実施中',
        ));

        $this->assertTrue($this->storage->delete());

        $this->assertNull($this->storage->get());
        $this->assertSame(1, $this->table()->count());
    }

    #[Test]
    public function 接続できればヘルスチェックが通る(): void
    {
        $this->assertTrue($this->storage->healthCheck());
    }

    #[Test]
    public function テーブルが存在しなければ例外を投げずに失敗を返す(): void
    {
        // 障害時にメンテ判定でリクエスト全体を落とさないことを確認する
        $storage = new DatabaseMaintenanceStorage(['table' => 'sys_maintenance_missing']);

        $this->assertFalse($storage->healthCheck());
        $this->assertNull($storage->get());
        $this->assertFalse($storage->put(new Maintenance(isMaintenance: true)));
        $this->assertFalse($storage->delete());
    }

    #[Test]
    public function driverがdatabaseならこの実装が解決される(): void
    {
        // TestCaseがメモリ実装を差し替えているため、
        // ServiceProviderのバインドをやり直して実行時の解決経路を確認する
        $this->assertInstanceOf(
            DatabaseMaintenanceStorage::class,
            $this->resolveStorageWithDriver('database')
        );
    }

    #[Test]
    public function 未対応のdriverは解決時に例外になる(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->resolveStorageWithDriver('memcached');
    }

    private function resolveStorageWithDriver(string $driver): MaintenanceStorageInterface
    {
        config(['maintenance.driver' => $driver]);

        $this->app->forgetInstance(MaintenanceStorageInterface::class);
        (new MaintenanceServiceProvider($this->app))->register();

        return $this->app->make(MaintenanceStorageInterface::class);
    }

    private function table(): Builder
    {
        return DB::connection('sys')->table('sys_maintenance');
    }
}
