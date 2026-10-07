<?php

namespace App\Domain\Version\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Version\DataTransferObjects\VersionResult;
use App\Domain\Version\Services\VersionService;

/**
 * CheckUseCase
 *
 * バージョンチェックのユースケース
 */
class CheckUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly VersionService $versionService
    ) {}

    /**
     * バージョンチェックを実行
     *
     * @param  int|null  $deployVersion  デプロイバージョン
     */
    public function exec(?int $deployVersion): VersionResult
    {
        // Serviceからデータを取得 [sysDeploy, sysMaintenance]
        [$sysDeploy, $sysMaintenance] = $this->versionService->checkVersion($deployVersion);

        // sysDeployがnullの場合は更新不要
        if ($sysDeploy === null) {
            return new VersionResult(needsUpdate: false, sysMaintenance: $sysMaintenance);
        }

        return new VersionResult(
            needsUpdate: true,
            sysDeploy: $sysDeploy,
            sysMaintenance: $sysMaintenance
        );
    }
}
