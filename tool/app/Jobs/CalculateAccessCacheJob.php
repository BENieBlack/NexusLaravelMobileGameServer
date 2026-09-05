<?php

namespace App\Jobs;

use App\Services\AccessCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * CalculateAccessCacheJob
 *
 * 指定日のアクセス統計を全 log シャードから集計し
 * tol_dashboard_access_cache にキャッシュする。
 */
class CalculateAccessCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function __construct(
        private readonly string $dateStr,
    ) {}

    public function handle(AccessCacheService $service): void
    {
        $service->calculateAndCache($this->dateStr);
    }
}
