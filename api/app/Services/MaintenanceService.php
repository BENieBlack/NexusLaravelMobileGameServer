<?php

namespace App\Services;

use App\Contracts\Maintenance\MaintenanceStorageInterface;
use App\DTOs\MaintenanceInfo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * メンテナンスサービス
 * 
 * メンテナンス状態の管理を担当
 * ストレージ（DynamoDB/TableStore）への読み書きをラップし、
 * キャッシュによる高速化も提供
 */
class MaintenanceService
{
    private const CACHE_KEY = 'maintenance:status:cache';

    public function __construct(
        private readonly MaintenanceStorageInterface $storage,
        private readonly int $cacheTtl = 60,
        private readonly bool $cacheEnabled = true,
    ) {}

    /**
     * メンテナンス中かどうかを判定
     * 
     * @return bool メンテナンス中の場合true
     */
    public function isUnderMaintenance(): bool
    {
        $info = $this->getMaintenanceInfo();
        
        if ($info === null) {
            return false;
        }

        return $info->isCurrentlyUnderMaintenance();
    }

    /**
     * メンテナンス情報を取得
     * 
     * @return MaintenanceInfo|null メンテナンス情報
     */
    public function getMaintenanceInfo(): ?MaintenanceInfo
    {
        // キャッシュから取得を試みる
        if ($this->cacheEnabled) {
            $cached = Cache::get(self::CACHE_KEY);
            if ($cached !== null) {
                return $cached;
            }
        }

        // ストレージから取得
        $info = $this->storage->get();

        // キャッシュに保存
        if ($info !== null && $this->cacheEnabled) {
            Cache::put(self::CACHE_KEY, $info, $this->cacheTtl);
        }

        return $info;
    }

    /**
     * メンテナンスを開始
     * 
     * @param MaintenanceInfo $info メンテナンス情報
     * @return bool 成功時true
     */
    public function startMaintenance(MaintenanceInfo $info): bool
    {
        $result = $this->storage->put($info);

        if ($result) {
            // キャッシュをクリア
            $this->clearCache();
            
            Log::info('Maintenance started', [
                'start_at' => $info->startAt?->toIso8601String(),
                'end_at' => $info->endAt?->toIso8601String(),
                'title' => $info->title,
            ]);
        }

        return $result;
    }

    /**
     * メンテナンスを終了
     * 
     * @return bool 成功時true
     */
    public function endMaintenance(): bool
    {
        $result = $this->storage->delete();

        if ($result) {
            // キャッシュをクリア
            $this->clearCache();
            
            Log::info('Maintenance ended');
        }

        return $result;
    }

    /**
     * ストレージの接続確認
     * 
     * @return bool 接続可能な場合true
     */
    public function healthCheck(): bool
    {
        return $this->storage->healthCheck();
    }

    /**
     * キャッシュをクリア
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
