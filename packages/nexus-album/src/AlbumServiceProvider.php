<?php

namespace NexusAlbum;

use Illuminate\Support\ServiceProvider;

/**
 * AlbumServiceProvider
 *
 * アルバム（収集記録）パッケージのサービスプロバイダー
 *
 * 提供機能:
 * - Enums: AlbumContentType
 * - DataTransferObjects: AlbumEntry
 * - ValueObjects: AlbumProgress
 * - Repositories: AlbumEntryRepositoryInterface / AlbumCatalogRepositoryInterface
 * - Services: AlbumService
 *
 * Repositoryの実装はアプリケーション側で束縛する。
 */
class AlbumServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // trx はシャードごとに流す（php artisan trx:migrate）、mst は通常のmigrate
        foreach (['trx', 'mst'] as $type) {
            $path = __DIR__."/../database/migrations/{$type}";

            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
