<?php

namespace NexusEvent;

use Illuminate\Support\ServiceProvider;

/**
 * EventServiceProvider
 *
 * イベント基盤パッケージのサービスプロバイダー
 *
 * 提供機能:
 * - Constants: EventContentType / EventPhase
 * - DataTransferObjects: Event / EventContent
 * - ValueObjects: ContentPeriod
 * - Contracts: EventMasterRepositoryInterface
 * - Services: EventService / EventPeriodResolver
 *
 * Repository の実装はアプリケーション側で束縛する。
 */
class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // trx / log はシャードごとに流す（trx:migrate / pitr:migrate）、mst は通常のmigrate
        foreach (['mst', 'trx', 'log'] as $type) {
            $path = __DIR__."/../database/migrations/{$type}";

            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
