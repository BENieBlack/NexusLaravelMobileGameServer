<?php

namespace Tests;

use App\Repositories\Mst\_BaseMstRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Nexus\Core\Models\_BaseModel;
use Nexus\Core\Models\Mst\_BaseMst;
use Nexus\Core\Utilities\ClockUtility;
use NexusMaintenance\Contracts\MaintenanceStorageInterface;
use NexusSecurity\Middleware\VerifyClientSignature;
use NexusUnitOfWork\Contracts\QueryManagerInterface;
use Tests\Support\InMemoryMaintenanceStorage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // テスト環境ではクライアント署名検証を無効化
        $this->withoutMiddleware(VerifyClientSignature::class);

        // メンテナンスストレージをメモリ実装に差し替える
        // （本番ドライバはAWS/Alibabaの外部SDKを必要とするためテストでは使えない）
        $this->app->singleton(
            MaintenanceStorageInterface::class,
            fn () => new InMemoryMaintenanceStorage
        );

        // テストではマスターデータを組み立てる必要があるため書き込みを許可する
        // （本番の実行時経路では _BaseMst が書き込みを拒否する）
        _BaseMst::allowWrites();

        // テストのフィクスチャはUnitOfWorkを介さずModelを直接投入するため許可する
        // （本番の実行時経路では _BaseModel が save() を拒否する）
        _BaseModel::allowDirectWrites();

        // Clockをリセット（各テストで独立した時刻を使用）
        ClockUtility::reset();
    }

    /**
     * Mstリポジトリのキャッシュをクリアする
     * テストでマスターデータを作成した後に呼び出すことで、
     * リポジトリが新しいデータを読み込むようにする
     */
    protected function refreshMstCache(): void
    {
        _BaseMstRepository::clearAllCaches();
    }

    /**
     * UnitOfWorkのキューをDBに反映する
     *
     * 本番ではUseCaseのトランザクション終了時にフラッシュされるため、
     * UseCaseを介さずService/Repositoryを直接検証するテストで、
     * DBの状態をアサートする前に呼び出す。
     */
    protected function flushQueue(): void
    {
        app()->make(QueryManagerInterface::class)->flush();
    }
}
