<?php

namespace App\Services;

use App\Jobs\CalculateAccessCacheJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AccessCacheService
 *
 * log_access（log1/log2/log3 全シャード）を日次集計し、
 * tol_dashboard_access_cache にキャッシュする。
 *
 * キャッシュ戦略（RetentionCacheService と同方式）:
 * - キャッシュ済みは即返す
 * - 未集計・キャッシュ切れはバックグラウンドジョブへ
 * - 当日は常に再集計ジョブ投入
 */
class AccessCacheService
{
    private const CACHE_TTL_HOURS = 24;
    private const CACHE_DAYS      = 90;  // 最大保持日数

    /** 全 log シャード接続名 */
    private const LOG_CONNECTIONS = ['log', 'log2', 'log3'];

    /**
     * 指定期間の日次アクセス集計をキャッシュから返す。
     * 未集計分はジョブに投げる。
     *
     * @param  string  $period  'all' | '1month' | '6months' | '1year' | '2weeks' | '1week' | '1day'
     * @return array{ labels: array, data: array, is_calculating: bool }
     */
    public function getAccessStats(string $period): array
    {
        [$startDate, $format] = $this->resolvePeriod($period);
        $today       = Carbon::today();
        $cacheExpiry = Carbon::now()->subHours(self::CACHE_TTL_HOURS);

        // キャッシュを一括取得
        $cached = DB::connection('tool')
            ->table('tol_dashboard_access_cache')
            ->where('access_date', '>=', $startDate->format('Y-m-d'))
            ->get()
            ->keyBy('access_date');

        $pendingCount = 0;
        $current      = $startDate->copy();
        $labels       = [];
        $data         = [];

        while ($current->lte($today)) {
            $dateStr  = $current->format('Y-m-d');
            $row      = $cached->get($dateStr);
            $isToday  = $current->isSameDay($today);

            $needsJob = $isToday
                || !$row
                || Carbon::parse($row->calculated_at)->lt($cacheExpiry);

            if ($needsJob) {
                CalculateAccessCacheJob::dispatch($dateStr)->onQueue('default');
                $pendingCount++;
            }

            $labels[] = $current->format($format);
            $data[]   = $row ? (int) $row->total_count : 0;

            $current->addDay();
        }

        return [
            'labels'         => $labels,
            'data'           => $data,
            'is_calculating' => $pendingCount > 0,
        ];
    }

    /**
     * 最新キャッシュを返す（ポーリング用）
     */
    public function getLatestStats(string $period): array
    {
        [$startDate, $format] = $this->resolvePeriod($period);
        $today = Carbon::today();

        $cached = DB::connection('tool')
            ->table('tol_dashboard_access_cache')
            ->where('access_date', '>=', $startDate->format('Y-m-d'))
            ->get()
            ->keyBy('access_date');

        $pendingJobs = DB::connection('tool')
            ->table('jobs')
            ->where('queue', 'default')
            ->count();

        $current = $startDate->copy();
        $labels  = [];
        $data    = [];

        while ($current->lte($today)) {
            $row      = $cached->get($current->format('Y-m-d'));
            $labels[] = $current->format($format);
            $data[]   = $row ? (int) $row->total_count : 0;
            $current->addDay();
        }

        return [
            'labels'         => $labels,
            'data'           => $data,
            'is_calculating' => $pendingJobs > 0,
        ];
    }

    /**
     * 指定日のアクセス数を全シャードから集計してキャッシュする（ジョブから呼ばれる）
     */
    public function calculateAndCache(string $dateStr): void
    {
        $totalCount  = 0;
        $uniqueUsers = [];
        $errorCount  = 0;

        foreach (self::LOG_CONNECTIONS as $conn) {
            try {
                $rows = DB::connection($conn)
                    ->table('log_access')
                    ->selectRaw('COUNT(*) as cnt, COUNT(DISTINCT sys_player_id) as uniq, SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errs')
                    ->whereRaw('DATE(system_at) = ?', [$dateStr])
                    ->first();

                if ($rows) {
                    $totalCount  += (int) $rows->cnt;
                    $errorCount  += (int) $rows->errs;
                    // ユニークユーザーはシャード間で重複する可能性があるため別途集計
                    $ids = DB::connection($conn)
                        ->table('log_access')
                        ->selectRaw('DISTINCT sys_player_id')
                        ->whereRaw('DATE(system_at) = ?', [$dateStr])
                        ->pluck('sys_player_id')
                        ->all();
                    foreach ($ids as $id) {
                        $uniqueUsers[$id] = true;
                    }
                }
            } catch (\Throwable) {
                // シャードに接続できない場合はスキップ
            }
        }

        $now = now()->format('Y-m-d H:i:s');

        DB::connection('tool')->table('tol_dashboard_access_cache')->upsert(
            [
                'access_date'   => $dateStr,
                'total_count'   => $totalCount,
                'unique_users'  => count($uniqueUsers),
                'error_count'   => $errorCount,
                'calculated_at' => $now,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            ['access_date'],
            ['total_count', 'unique_users', 'error_count', 'calculated_at', 'updated_at']
        );
    }

    /**
     * 期間文字列から開始日と日付フォーマットを解決する
     *
     * @return array{ Carbon, string }
     */
    private function resolvePeriod(string $period): array
    {
        $today = Carbon::today();

        return match ($period) {
            '1day'    => [$today->copy()->subDays(1),   'm/d'],
            '1week'   => [$today->copy()->subDays(6),   'm/d'],
            '2weeks'  => [$today->copy()->subDays(13),  'm/d'],
            '1month'  => [$today->copy()->subDays(29),  'm/d'],
            '6months' => [$today->copy()->subDays(179), 'Y/m/d'],
            '1year'   => [$today->copy()->subDays(364), 'Y/m/d'],
            'all'     => [$this->getOldestDate(),       'Y/m/d'],
            default   => [$today->copy()->subDays(29),  'm/d'],
        };
    }

    /**
     * 全シャードの最古アクセス日を取得する
     */
    private function getOldestDate(): Carbon
    {
        $oldest = null;
        foreach (self::LOG_CONNECTIONS as $conn) {
            try {
                $min = DB::connection($conn)
                    ->table('log_access')
                    ->min('system_at');
                if ($min && (!$oldest || $min < $oldest)) {
                    $oldest = $min;
                }
            } catch (\Throwable) {
            }
        }
        return $oldest ? Carbon::parse($oldest)->startOfDay() : Carbon::today()->subYear();
    }
}
