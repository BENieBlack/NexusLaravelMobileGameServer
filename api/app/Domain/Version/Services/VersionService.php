<?php

namespace App\Domain\Version\Services;

use App\Exceptions\SystemDataException;
use App\Models\Sys\SysDeploy;
use App\Models\Sys\SysMaintenance;
use App\Repositories\Sys\SysDeployRepository;
use App\Repositories\Sys\SysMaintenanceRepository;
use NexusVersion\Services\VersionService as BaseVersionService;

/**
 * VersionService
 *
 * パッケージ版のVersionServiceのラッパー
 * Eloquent Modelを返すために変換処理を行う
 */
class VersionService
{
    public function __construct(
        private readonly SysDeployRepository $sysDeployRepository,
        private readonly SysMaintenanceRepository $sysMaintenanceRepository,
        private readonly BaseVersionService $baseVersionService
    ) {}

    /**
     * バージョンチェックを実行
     *
     * @param  int|null  $currentDeployId  クライアントが保持しているデプロイID
     * @return array{0: SysDeploy|null, 1: SysMaintenance|null} [sysDeploy, sysMaintenance]
     *
     * @throws SystemDataException ダウンロード可能なデプロイが存在しない場合
     */
    public function checkVersion(?int $currentDeployId): array
    {
        try {
            // パッケージ版のServiceを呼び出し
            $result = $this->baseVersionService->checkVersion($currentDeployId);

            // 配列からEloquent Modelに変換
            $sysDeploy = $result['deploy'] ? $this->sysDeployRepository->selectById($result['deploy']['id']) : null;
            $sysMaintenance = $result['maintenance'] ? $this->sysMaintenanceRepository->selectCurrentMaintenance() : null;

            return [$sysDeploy, $sysMaintenance];
        } catch (\RuntimeException $e) {
            throw SystemDataException::deploy();
        }
    }
}
