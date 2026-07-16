<?php

namespace NexusMaintenance\Services;

use NexusMaintenance\Contracts\MaintenanceStorageInterface;
use NexusMaintenance\DTOs\MaintenanceDto;
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
        $sysMaintenance = $this->getMaintenanceInfo();
        
        if ($sysMaintenance === null) {
            return false;
        }

        return $sysMaintenance->isCurrentlyUnderMaintenance();
    }

    /**
     * メンテナンス情報を取得
     * 
     * @return MaintenanceDto|null メンテナンス情報
     */
    public function getMaintenanceInfo(): ?MaintenanceDto
    {
        // キャッシュから取得を試みる
        if ($this->cacheEnabled) {
            $cached = Cache::get(self::CACHE_KEY);
            if ($cached !== null) {
                return $cached;
            }
        }

        // ストレージから取得
        $sysMaintenance = $this->storage->get();

        // キャッシュに保存
        if ($sysMaintenance !== null && $this->cacheEnabled) {
            Cache::put(self::CACHE_KEY, $sysMaintenance, $this->cacheTtl);
        }

        return $sysMaintenance;
    }

    /**
     * メンテナンスを開始
     * 
     * @param MaintenanceDto $sysMaintenance メンテナンス情報
     * @return bool 成功時true
     */
    public function startMaintenance(MaintenanceDto $sysMaintenance): bool
    {
        $result = $this->storage->put($sysMaintenance);

        if ($result) {
            // キャッシュをクリア
            $this->clearCache();
            
            Log::info('Maintenance started', [
                'start_at' => $sysMaintenance->startAt,
                'end_at' => $sysMaintenance->endAt,
                'title' => $sysMaintenance->title,
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
        if ($this->cacheEnabled) {
            Cache::forget(self::CACHE_KEY);
        }
    }
}
