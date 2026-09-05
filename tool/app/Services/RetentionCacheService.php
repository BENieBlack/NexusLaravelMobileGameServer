<?php

namespace App\Services;

use App\Jobs\CalculateRetentionJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * RetentionCacheService
 *
 * log_access から日次コホート継続率を集計し、
 * tol_dashboard_retention テーブルにキャッシュする。
 *
 * キャッシュ戦略:
 * - キャッシュ済みのコホートは即返す
 * - 未集計・キャッシュ切れのコホートはバックグラウンドジョブに投げて非同期集計
 * - 当日コホートは常に再集計ジョブを投げる（ユーザー数が変動するため）
 */
class RetentionCacheService
{
    private const CACHE_TTL_HOURS = 24;
    public const RETENTION_DAYS   = [1, 2, 3, 4, 5, 6, 7, 14, 30, 60, 90];
    private const COHORT_DAYS     = 30;

    /**
     * キャッシュ済みの継続率を即返し、未集計分はバックグラウンドジョブに投げる
     *
     * @return array{ rows: array, is_calculating: bool }
     */
    public function getRetentionStatsWithStatus(): array
    {
        $today       = Carbon::today()->format('Y-m-d');
        $fromDate    = Carbon::today()->subDays(self::COHORT_DAYS)->format('Y-m-d');
        $cacheExpiry = Carbon::now()->subHours(self::CACHE_TTL_HOURS);

        $cached = DB::connection('tool')
            ->table('tol_dashboard_retention')
            ->whereBetween('cohort_date', [$fromDate, $today])
            ->get()
            ->keyBy('cohort_date');

        $cohortDates = [];
        for ($i = self::COHORT_DAYS; $i >= 0; $i--) {
            $cohortDates[] = Carbon::today()->subDays($i)->format('Y-m-d');
        }

        $rows          = [];
        $pendingCount  = 0;

        foreach ($cohortDates as $cohortDate) {
            $row     = $cached->get($cohortDate);
            $isToday = ($cohortDate === $today);

            $needsJob = $isToday
                || !$row
                || Carbon::parse($row->calculated_at)->lt($cacheExpiry);

            if ($needsJob) {
                CalculateRetentionJob::dispatch($cohortDate)->onQueue('default');
                $pendingCount++;

                // 古いキャッシュがあれば暫定表示に使う
                if ($row && (int) $row->new_users > 0) {
                    $rows[] = $this->toArray($row);
                }
            } else {
                if ($row && (int) $row->new_users > 0) {
                    $rows[] = $this->toArray($row);
                }
            }
        }

        return [
            'rows'           => $rows,
            'is_calculating' => $pendingCount > 0,
        ];
    }

    /**
     * 最新キャッシュデータを返す（ポーリング用）
     *
     * @return array{ rows: array, is_calculating: bool }
     */
    public function getLatestCachedStats(): array
    {
        $today    = Carbon::today()->format('Y-m-d');
        $fromDate = Carbon::today()->subDays(self::COHORT_DAYS)->format('Y-m-d');

        $cached = DB::connection('tool')
            ->table('tol_dashboard_retention')
            ->whereBetween('cohort_date', [$fromDate, $today])
            ->get()
            ->keyBy('cohort_date');

        $pendingJobs = DB::connection('tool')
            ->table('jobs')
            ->where('queue', 'default')
            ->count();

        $rows = [];
        for ($i = self::COHORT_DAYS; $i >= 0; $i--) {
            $cohortDate = Carbon::today()->subDays($i)->format('Y-m-d');
            $row = $cached->get($cohortDate);
            if ($row && (int) $row->new_users > 0) {
                $rows[] = $this->toArray($row);
            }
        }

        return [
            'rows'           => $rows,
            'is_calculating' => $pendingJobs > 0,
        ];
    }

    /**
     * 指定コホート日の継続率を集計してDBに保存する（ジョブから呼ばれる）
     */
    public function calculateAndCache(string $cohortDate): ?object
    {
        $today  = Carbon::today();
        $cohort = Carbon::parse($cohortDate);

        $newUserRows = DB::connection('log')
            ->table('log_access')
            ->selectRaw('sys_player_id, MIN(DATE(system_at)) as first_day')
            ->groupBy('sys_player_id')
            ->havingRaw('first_day = ?', [$cohortDate])
            ->get();

        $newUsers = $newUserRows->count();

        if ($newUsers === 0) {
            $this->upsert($cohortDate, 0, array_fill_keys(
                array_map(fn($d) => "d{$d}", self::RETENTION_DAYS), null
            ));
            return DB::connection('tool')
                ->table('tol_dashboard_retention')
                ->where('cohort_date', $cohortDate)->first();
        }

        $playerIds = $newUserRows->pluck('sys_player_id')->all();

        $allVisits = DB::connection('log')
            ->table('log_access')
            ->whereIn('sys_player_id', $playerIds)
            ->selectRaw('sys_player_id, DATE(system_at) as visit_date')
            ->distinct()
            ->get()
            ->groupBy('sys_player_id')
            ->map(fn($rows) => $rows->pluck('visit_date')->flip()->all());

        $retentionData = [];
        foreach (self::RETENTION_DAYS as $days) {
            $targetDate = $cohort->copy()->addDays($days)->format('Y-m-d');
            if (Carbon::parse($targetDate)->gt($today)) {
                $retentionData["d{$days}"] = null;
                continue;
            }
            $retained = 0;
            foreach ($playerIds as $pid) {
                if (isset($allVisits->get($pid, [])[$targetDate])) {
                    $retained++;
                }
            }
            $retentionData["d{$days}"] = round($retained / $newUsers * 100, 2);
        }

        $this->upsert($cohortDate, $newUsers, $retentionData);

        return DB::connection('tool')
            ->table('tol_dashboard_retention')
            ->where('cohort_date', $cohortDate)->first();
    }

    private function upsert(string $cohortDate, int $newUsers, array $retentionData): void
    {
        $now = now()->format('Y-m-d H:i:s');
        DB::connection('tool')->table('tol_dashboard_retention')->upsert(
            array_merge(['cohort_date' => $cohortDate, 'new_users' => $newUsers,
                'calculated_at' => $now, 'created_at' => $now, 'updated_at' => $now], $retentionData),
            ['cohort_date'],
            array_merge(['new_users', 'calculated_at', 'updated_at'], array_keys($retentionData))
        );
    }

    private function toArray(mixed $row): array
    {
        $r = is_object($row) ? (array) $row : $row;
        $result = ['cohort_date' => $r['cohort_date'], 'new_users' => (int) $r['new_users']];
        foreach (self::RETENTION_DAYS as $d) {
            $key = "d{$d}";
            $result[$key] = (isset($r[$key]) && $r[$key] !== null) ? (float) $r[$key] : null;
        }
        return $result;
    }
}
