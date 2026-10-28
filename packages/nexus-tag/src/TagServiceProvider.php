<?php

namespace NexusTag;

use Illuminate\Support\ServiceProvider;

/**
 * TagServiceProvider
 *
 * 汎用タグパッケージのサービスプロバイダー
 *
 * 提供機能:
 * - DataTransferObjects: TagContent
 * - Repositories: TagRepositoryInterface
 * - Services: TagService
 *
 * Repositoryの実装はアプリケーション側で束縛する。
 */
class TagServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $path = __DIR__.'/../database/migrations/mst';

        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }
}
