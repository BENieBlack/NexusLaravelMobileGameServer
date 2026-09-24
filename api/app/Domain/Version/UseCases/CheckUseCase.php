<?php

namespace App\Domain\Version\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Version\Services\VersionService;
use App\Http\Responses\Auth\VersionResponse;

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
    public function exec(?int $deployVersion): VersionResponse
    {
        // Serviceからデータを取得 [sysDeploy, sysMaintenance]
        [$sysDeploy, $sysMaintenance] = $this->versionService->checkVersion($deployVersion);

        // UseCaseでResponseを合成
        // sysDeployがnullの場合は更新不要
        if ($sysDeploy === null) {
            return VersionResponse::upToDate($sysMaintenance);
        }

        return VersionResponse::updateAvailable(
            sysDeploy: $sysDeploy,
            sysMaintenance: $sysMaintenance
        );
    }
}
