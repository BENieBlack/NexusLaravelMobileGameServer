<?php

namespace NexusUnitOfWork;

use Illuminate\Support\ServiceProvider;
use NexusUnitOfWork\Contracts\QueryManagerInterface;
use NexusUnitOfWork\Persistence\QueryManager;

/**
 * UnitOfWorkServiceProvider
 * 
 * Laravel Unit of Work パッケージのサービスプロバイダー
 */
class UnitOfWorkServiceProvider extends ServiceProvider
{
    /**
     * サービスの登録
     *
     * @return void
     */
    public function register(): void
    {
        // 設定ファイルをマージ
        $this->mergeConfigFrom(
            __DIR__.'/../config/unit-of-work.php',
            'unit-of-work'
        );

        // P2-5: QueryManagerをscopedバインドに変更（リクエストスコープ）
        // singleton から scoped へ変更することで、リクエストごとに新しいインスタンスを生成
        // これにより、リクエスト間でのデータ混在を防ぎ、メモリリークを防止
        $this->app->scoped(QueryManagerInterface::class, function ($app) {
            return new QueryManager();
        });

        // QueryManagerクラスとしても登録（後方互換性のため）
        $this->app->scoped(QueryManager::class, function ($app) {
            return $app->make(QueryManagerInterface::class);
        });
    }

    /**
     * サービスの起動
     *
     * @return void
     */
    public function boot(): void
    {
        // 設定ファイルの公開
        $this->publishes([
            __DIR__.'/../config/unit-of-work.php' => config_path('unit-of-work.php'),
        ], 'unit-of-work-config');

        // マイグレーションファイルの公開（もし必要なら）
        // $this->publishes([
        //     __DIR__.'/../database/migrations' => database_path('migrations'),
        // ], 'unit-of-work-migrations');
    }

    /**
     * パッケージが提供するサービス
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            QueryManagerInterface::class,
            QueryManager::class,
        ];
    }
}
