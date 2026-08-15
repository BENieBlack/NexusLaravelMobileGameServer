<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysMaintenance;
use Illuminate\Support\Facades\Cache;
use Nexus\Core\Support\CustomCollection;
use NexusVersion\Repositories\MaintenanceRepositoryInterface;

/**
 * SysMaintenanceRepository
 *
 * メンテナンス情報のRepository実装
 * 例外的に Redis キャッシュを使用
 *
 * @extends _BaseSysRepository<SysMaintenance>
 */
class SysMaintenanceRepository extends _BaseSysRepository implements MaintenanceRepositoryInterface
{
    protected string $modelClass = SysMaintenance::class;

    /**
     * 現在進行中のメンテナンスを取得（Redis キャッシュ付き）
     */
    public function selectCurrentMaintenance(): ?SysMaintenance
    {
        $cacheKey = $this->buildCacheKey('current_maintenance');

        return Cache::store($this->cacheDriver)->remember(
            $cacheKey,
            $this->cacheTtl,
            function () {
                $now = now();
                $sysMaintenance = $this->modelClass::where('is_active', true)
                    ->where('start_at', '<=', $now)
                    ->where(function ($q) use ($now) {
                        $q->whereNull('end_at')
                            ->orWhere('end_at', '>', $now);
                    })
                    ->orderBy('start_at', 'desc')
                    ->first();

                // メモリキャッシュにも保存
                if ($sysMaintenance !== null) {
                    $this->setModel($sysMaintenance);
                }

                return $sysMaintenance;
            }
        );
    }

    /**
     * {@inheritDoc}
     * MaintenanceRepositoryInterface実装
     */
    public function selectCurrent(): ?array
    {
        $sysMaintenance = $this->selectCurrentMaintenance();

        return $sysMaintenance ? $sysMaintenance->toArray() : null;
    }

    /**
     * 有効なメンテナンス一覧を取得
     */
    public function selectActiveList(): CustomCollection
    {
        return $this->modelClass::where('is_active', true)
            ->orderBy('start_at', 'desc')
            ->get();
    }

    /**
     * 今後予定されているメンテナンス一覧を取得
     */
    public function selectUpcomingList(): CustomCollection
    {
        return $this->modelClass::where('is_active', true)
            ->where('start_at', '>', now())
            ->orderBy('start_at', 'asc')
            ->get();
    }
}
