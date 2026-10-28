<?php

namespace NexusAgreement;

use Illuminate\Support\ServiceProvider;

/**
 * AgreementServiceProvider
 *
 * 利用規約パッケージのサービスプロバイダー
 *
 * 提供機能:
 * - DataTransferObjects: PendingAgreement
 * - Models: SysAgreement / TrxAgreementConsent
 * - Repositories: AgreementRepositoryInterface
 * - Services: AgreementService
 *
 * Repositoryの実装はアプリケーション側で束縛する。
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
