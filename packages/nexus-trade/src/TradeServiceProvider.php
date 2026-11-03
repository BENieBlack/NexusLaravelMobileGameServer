<?php

namespace NexusTrade;

use Illuminate\Support\ServiceProvider;

/**
 * TradeServiceProvider
 *
 * 交換（交換所）パッケージのサービスプロバイダー
 *
 * 提供機能:
 * - DataTransferObjects: Trade / TradeProduct / TradeProductContent / TradeProductCount
 * - ValueObjects: TradeProductStatus
 * - Contracts: TradeMasterRepositoryInterface / TradeCostPayerInterface
 * - Repositories: TradeProductCountRepositoryInterface / TradeLogRepositoryInterface
 * - Services: TradeService
 *
 * Repository と支払いの実装はアプリケーション側で束縛する。
 */
class TradeServiceProvider extends ServiceProvider
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
