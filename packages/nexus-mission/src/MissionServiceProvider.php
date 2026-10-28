<?php

namespace NexusMission;

use Illuminate\Support\ServiceProvider;

/**
 * MissionServiceProvider
 *
 * ミッションパッケージのサービスプロバイダー
 *
 * 提供機能:
 * - DataTransferObjects: Mission / MissionContent / Progress / MissionReceipt
 * - ValueObjects: MissionTrigger / MissionStatus
 * - Contracts: MissionMasterRepositoryInterface
 * - Repositories: ProgressRepositoryInterface / MissionReceiptRepositoryInterface
 * - Services: MissionService
 *
 * Repositoryの実装はアプリケーション側で束縛する。
 */
class MissionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // trx はシャードごとに流す（php artisan trx:migrate）、mst は通常のmigrate
        foreach (['mst', 'trx'] as $type) {
            $path = __DIR__."/../database/migrations/{$type}";

            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
