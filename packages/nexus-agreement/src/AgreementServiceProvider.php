<?php

namespace NexusAgreement;

use Illuminate\Support\ServiceProvider;

/**
 * AgreementServiceProvider
 *
 * 利用規約パッケージのサービスプロバイダー
 *
 * 提供機能:
 * - DataTransferObjects: Agreement / PendingAgreement
 * - Repositories: AgreementRepositoryInterface
 * - Services: AgreementService
 *
 * Model と Repository の実装はアプリケーション側に置き、
 * AgreementRepositoryInterface を AgreementRepositoryAdapter で束縛する。
 */
class AgreementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // sys は通常の migrate、trx はシャードごとに流す
        foreach (['sys', 'trx'] as $type) {
            $path = __DIR__.'/../database/migrations/'.$type;

            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }
}
