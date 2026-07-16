<?php

namespace App\Domain\Version\Services;

use App\Exceptions\SystemDataException;
use App\Models\Sys\SysDeploy;
use App\Models\Sys\SysMaintenance;
use App\Repositories\Sys\SysDeployRepository;
use App\Repositories\Sys\SysMaintenanceRepository;

class VersionService
{
    /**
     * コンストラクタ
     *
     * @param SysDeployRepository $sysDeployRepository
     * @param SysMaintenanceRepository $sysMaintenanceRepository
     */
    public function __construct(
        private readonly SysDeployRepository $sysDeployRepository,
        private readonly SysMaintenanceRepository $sysMaintenanceRepository
    ) {
    }
    /**
     * バージョンチェックを実行
     *
     * @param int|null $currentDeployId クライアントが保持しているデプロイID
     * @return array{0: SysDeploy|null, 1: SysMaintenance|null} [sysDeploy, sysMaintenance]
     * @throws SystemDataException ダウンロード可能なデプロイが存在しない場合
     */
    public function checkVersion(?int $currentDeployId): array
    {
        // メンテナンス情報を取得
        $sysMaintenance = $this->sysMaintenanceRepository->selectCurrentMaintenance();

        // 最新のダウンロード可能なデプロイを取得（リレーション込み）
        $sysDeploy = $this->sysDeployRepository->selectLatestDownloadable();

        // 最新のデプロイが存在しない場合（あり得ないケース）
        if ($sysDeploy === null) {
            throw SystemDataException::deploy();
        }

        // リレーションを読み込む
        $sysDeploy->load(['deployMaster', 'deployAsset']);

        // クライアントが最新の場合
        if ($currentDeployId === $sysDeploy->id) {
            // DLしないといけないデータは存在しないが、メンテナンス情報は返す
            return [null, $sysMaintenance];
        }

        return [$sysDeploy, $sysMaintenance];
    }
}
