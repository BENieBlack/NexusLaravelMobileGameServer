<?php

namespace App\Repositories\Sys;


use NexusPersistence\Support\CustomCollection;
use App\Models\Sys\SysDeploy;
use NexusVersion\Repositories\DeployRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * SysDeployRepository
 * 
 * デプロイ情報のRepository実装
 * 例外的に Redis キャッシュを使用
 * 
 * @extends _BaseSysRepository<SysDeploy>
 */
class SysDeployRepository extends _BaseSysRepository implements DeployRepositoryInterface
{
    protected string $modelClass = SysDeploy::class;

    /**
     * 最新のダウンロード可能なデプロイを取得（Redis キャッシュ付き）
     *
     * @return SysDeploy|null
     */
    public function selectLatestDownloadable(): ?SysDeploy
    {
        $cacheKey = $this->getCacheKey("latest_downloadable");
        
        return Cache::store($this->cacheDriver)->remember(
            $cacheKey,
            $this->cacheTtl,
            function () {
                $sysDeploy = $this->modelClass::with(['deployMaster', 'deployAsset'])
                    ->where('is_active', true)
                    ->where('start_at', '<=', now())
                    ->orderBy('deploy_key', 'desc')
                    ->first();

                // メモリキャッシュにも保存
                if ($sysDeploy !== null) {
                    $this->setModel($sysDeploy);
                }

                return $sysDeploy;
            }
        );
    }

    /**
     * {@inheritDoc}
     * DeployRepositoryInterface実装
     */
    public function findLatestDownloadable(): ?array
    {
        $sysDeploy = $this->selectLatestDownloadable();
        return $sysDeploy ? $this->toArray($sysDeploy) : null;
    }

    /**
     * {@inheritDoc}
     * DeployRepositoryInterface実装
     */
    public function findById(int $deployId): ?array
    {
        $sysDeploy = $this->selectById($deployId);
        return $sysDeploy ? $this->toArray($sysDeploy) : null;
    }

    /**
     * デプロイキーからデプロイを検索（Redis キャッシュ付き）
     *
     * @param int $deployKey
     * @return SysDeploy|null
     */
    public function selectByDeployKey(int $deployKey): ?SysDeploy
    {
        $cacheKey = $this->getCacheKey("deploy_key:{$deployKey}");
        
        return Cache::store($this->cacheDriver)->remember(
            $cacheKey,
            $this->cacheTtl,
            function () use ($deployKey) {
                $sysDeploy = $this->modelClass::with(['deployMaster', 'deployAsset'])
                    ->where('deploy_key', $deployKey)
                    ->first();

                // メモリキャッシュにも保存
                if ($sysDeploy !== null) {
                    $this->setModel($sysDeploy);
                }

                return $sysDeploy;
            }
        );
    }

    /**
     * 有効なデプロイ一覧を取得
     *
     * @return Collection
     */
    public function selectActiveList(): CustomCollection
    {
        return $this->modelClass::where('is_active', true)
            ->orderBy('deploy_key', 'desc')
            ->get();
    }

    /**
     * IDで検索（オーバーライドして Redis キャッシュ付き、リレーション付き）
     *
     * @param int $sysDeployId
     * @return SysDeploy|null
     */
    public function selectById(int $sysDeployId): ?SysDeploy
    {
        $cacheKey = $this->getCacheKey("id:{$sysDeployId}");

        return Cache::store($this->cacheDriver)->remember(
            $cacheKey,
            $this->cacheTtl,
            function () use ($sysDeployId) {
                $sysDeploy = $this->modelClass::with(['deployMaster', 'deployAsset'])
                    ->find($sysDeployId);

                // メモリキャッシュにも保存
                if ($sysDeploy !== null) {
                    $this->setModel($sysDeploy);
                }

                return $sysDeploy;
            }
        );
    }

    /**
     * SysDeployモデルを配列に変換
     * 
     * @param SysDeploy $sysDeploy
     * @return array
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
