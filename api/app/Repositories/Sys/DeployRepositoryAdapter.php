<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysDeploy;
use NexusVersion\Repositories\DeployRepositoryInterface;

/**
 * DeployRepositoryAdapter
 *
 * nexus-versionパッケージのDeployRepositoryInterfaceを実装し、
 * Application層のSysDeployRepositoryをラップする。
 *
 * Repositoryは常にModelを返し、配列への詰め替えはこのアダプタが担う。
 */
class DeployRepositoryAdapter implements DeployRepositoryInterface
{
    public function __construct(
        private readonly SysDeployRepository $sysDeployRepository,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>|null
     */
    public function selectLatestDownloadable(): ?array
    {
        $sysDeploy = $this->sysDeployRepository->selectLatestDownloadable();

        return $sysDeploy ? $this->toArray($sysDeploy) : null;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, mixed>|null
     */
    public function selectById(int $deployId): ?array
    {
        $sysDeploy = $this->sysDeployRepository->selectById($deployId);

        return $sysDeploy ? $this->toArray($sysDeploy) : null;
    }

    /**
     * Eloquent Modelを配列に変換
     *
     * @return array<string, mixed>
     */
    private function toArray(SysDeploy $sysDeploy): array
    {
        $data = $sysDeploy->toArray();

        // リレーションも含める
        if ($sysDeploy->relationLoaded('deployMaster')) {
            $data['deploy_master'] = $sysDeploy->deployMaster?->toArray();
        }

        if ($sysDeploy->relationLoaded('deployAsset')) {
            $data['deploy_asset'] = $sysDeploy->deployAsset?->toArray();
        }

        return $data;
    }
}
