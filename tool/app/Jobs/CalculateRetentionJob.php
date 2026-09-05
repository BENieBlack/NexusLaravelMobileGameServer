<?php

namespace App\Jobs;

use App\Services\RetentionCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * CalculateRetentionJob
 *
 * 指定コホート日の継続率をバックグラウンドで集計してキャッシュする。
 * タイムアウトを避けるためダッシュボード表示と分離している。
 */
class CalculateRetentionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * ジョブの最大試行回数
     */
    public int $tries = 3;

    /**
     * ジョブのタイムアウト秒数
     */
    public int $timeout = 300;

    public function __construct(
        private readonly string $cohortDate,
    ) {}

    public function handle(RetentionCacheService $service): void
    {
        $service->calculateAndCache($this->cohortDate);
    }
}
