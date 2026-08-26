<?php

namespace NexusVersion\Services;

use NexusVersion\Repositories\DeployRepositoryInterface;
use NexusVersion\Repositories\MaintenanceRepositoryInterface;

/**
 * VersionService
 * 
 * バージョンチェックのビジネスロジック
 */
class VersionService
{
    public function __construct(
        private readonly DeployRepositoryInterface $deployRepository,
        private readonly MaintenanceRepositoryInterface $maintenanceRepository
    ) {
    }

    /**
     * バージョンチェックを実行
     *
     * @param int|null $currentDeployId クライアントが保持しているデプロイID
     * @return array{deploy: array<string, mixed>|null, maintenance: array<string, mixed>|null}
     * @throws \RuntimeException ダウンロード可能なデプロイが存在しない場合
     */
    public function checkVersion(?int $currentDeployId): array
    {
        // メンテナンス情報を取得
        $maintenance = $this->maintenanceRepository->selectCurrent();

        // 最新のダウンロード可能なデプロイを取得
        $latestDeploy = $this->deployRepository->selectLatestDownloadable();

        // 最新のデプロイが存在しない場合（あり得ないケース）
        if ($latestDeploy === null) {
            throw new \RuntimeException('No downloadable deploy found');
        }

        // クライアントが最新の場合
        if ($currentDeployId === $latestDeploy['id']) {
            // DLしないといけないデータは存在しないが、メンテナンス情報は返す
            return [
                'deploy' => null,
                'maintenance' => $maintenance,
            ];
        }

        return [
            'deploy' => $latestDeploy,
            'maintenance' => $maintenance,
        ];
    }
}
