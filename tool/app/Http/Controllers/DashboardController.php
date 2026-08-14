<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * ダッシュボードを表示
     */
    public function index(Request $request)
    {
        $period = $request->input('period', '1day');
        $revenuePeriod = $request->input('revenuePeriod', '1month');
        
        // 表示期間のアクセス統計を取得
        $accessStats = $this->selectAccessStats($period);
        
        // 表示期間の売上統計を取得
        $revenueStats = $this->selectRevenueStats($revenuePeriod);

        return Inertia::render('Dashboard', [
            'accessStats' => $accessStats,
            'revenueStats' => $revenueStats,
            'currentPeriod' => $period,
            'currentRevenuePeriod' => $revenuePeriod,
        ]);
    }

    /**
     * 指定期間のアクセス数を取得
     * 
     * @param string $period '1day', '1week', '2weeks', '1month', '6months', '1year', 'all'
     */
    private function selectAccessStats($period = '1day')
    {
        $now = now();
        
        // 期間に応じて開始時刻と集計間隔を設定
        switch ($period) {
            case '1day':
                $startTime = $now->copy()->subHours(24);
                $interval = 30; // 30分
                $format = 'H:i';
                $groupBy = '30min';
                break;
            case '1week':
                $startTime = $now->copy()->subWeek();
                $interval = 120; // 2時間
                $format = 'm/d H:i';
                $groupBy = '2hour';
                break;
            case '2weeks':
                $startTime = $now->copy()->subWeeks(2);
                $interval = 240; // 4時間
                $format = 'm/d H:i';
                $groupBy = '4hour';
                break;
            case '6months':
                $startTime = $now->copy()->subMonths(6);
                $interval = 10080; // 1週間（7日）
                $format = 'Y/m/d';
                $groupBy = 'week';
                break;
            case '1year':
                $startTime = $now->copy()->subYear();
                $interval = 43200; // 1ヶ月（30日）
                $format = 'Y/m';
                $groupBy = 'month';
                break;
            case 'all':
                // 最古のデータから取得
                $oldestRecord = DB::connection('log')
                    ->table('log_access')
                    ->orderBy('system_at', 'asc')
                    ->first();
                $startTime = $oldestRecord ? \Carbon\Carbon::parse($oldestRecord->system_at) : $now->copy()->subYear();
                $interval = 43200; // 1ヶ月（30日）
                $format = 'Y/m';
                $groupBy = 'month';
                break;
            case '1month':
            default:
                $startTime = $now->copy()->subMonth();
                $interval = 1440; // 1日
                $format = 'm/d';
                $groupBy = 'day';
                break;
        }

        // 時間を適切な単位に丸める
        if ($period === '1day') {
            $minute = (int) $startTime->format('i');
            if ($minute < 30) {
                $startTime->minute(0)->second(0);
            } else {
                $startTime->minute(30)->second(0);
            }
        } elseif (in_array($period, ['1month', '6months', '1year', 'all'])) {
            $startTime->startOfDay();
        } else {
            $startTime->minute(0)->second(0);
            $hour = (int) $startTime->format('H');
            if ($period === '1week') {
                $startTime->hour(intval($hour / 2) * 2);
            } elseif ($period === '2weeks') {
                $startTime->hour(intval($hour / 4) * 4);
            }
        }

        // 時間枠を生成
        $timeSlots = [];
        $labels = [];
        $current = $startTime->copy();
        
        while ($current <= $now) {
            if (in_array($period, ['1month', '6months'])) {
                // 日単位
                $timeSlots[] = $current->format('Y-m-d 00:00:00');
                $labels[] = $current->format($format);
            } elseif (in_array($period, ['1year', 'all'])) {
                // 月単位
                $timeSlots[] = $current->format('Y-m-01 00:00:00');
                $labels[] = $current->format($format);
            } elseif ($period === '1day') {
                // 30分単位
                $timeSlots[] = $current->format('Y-m-d H:i:00');
                $labels[] = $current->format($format);
            } else {
                // 時間単位（1week, 2weeks）
                $timeSlots[] = $current->format('Y-m-d H:00:00');
                $labels[] = $current->format($format);
            }
            $current->addMinutes($interval);
        }

        // log_accessテーブルから集計
        if (in_array($period, ['1month', '6months'])) {
            // 1ヶ月・半年: 日単位で集計
            $accessData = DB::connection('log')
                ->table('log_access')
                ->selectRaw("
                    DATE_FORMAT(system_at, '%Y-%m-%d 00:00:00') as time_slot,
                    COUNT(*) as count
                ")
                ->where('system_at', '>=', $startTime)
                ->where('system_at', '<=', $now)
                ->groupBy('time_slot')
                ->orderBy('time_slot')
                ->get()
                ->keyBy('time_slot');
        } elseif (in_array($period, ['1year', 'all'])) {
            // 1年・全期間: 月単位で集計
            $accessData = DB::connection('log')
                ->table('log_access')
                ->selectRaw("
                    DATE_FORMAT(system_at, '%Y-%m-01 00:00:00') as time_slot,
                    COUNT(*) as count
                ")
                ->where('system_at', '>=', $startTime)
                ->where('system_at', '<=', $now)
                ->groupBy('time_slot')
                ->orderBy('time_slot')
                ->get()
                ->keyBy('time_slot');
        } elseif ($period === '1day') {
            // 1日: 30分単位で集計
            $accessData = DB::connection('log')
                ->table('log_access')
                ->selectRaw("
                    CONCAT(
                        DATE_FORMAT(system_at, '%Y-%m-%d %H:'),
                        CASE 
                            WHEN MINUTE(system_at) < 30 THEN '00:00'
                            ELSE '30:00'
                        END
                    ) as time_slot,
                    COUNT(*) as count
                ")
                ->where('system_at', '>=', $startTime)
                ->where('system_at', '<=', $now)
                ->groupBy('time_slot')
                ->orderBy('time_slot')
                ->get()
                ->keyBy('time_slot');
        } else {
            // 1週間、2週間: 2時間または4時間単位で集計
            $hours = $period === '1week' ? 2 : 4;
            $accessData = DB::connection('log')
                ->table('log_access')
                ->selectRaw("
                    CONCAT(
                        DATE_FORMAT(system_at, '%Y-%m-%d '),
                        LPAD((HOUR(system_at) DIV {$hours}) * {$hours}, 2, '0'),
                        ':00:00'
                    ) as time_slot,
                    COUNT(*) as count
                ")
                ->where('system_at', '>=', $startTime)
                ->where('system_at', '<=', $now)
                ->groupBy('time_slot')
                ->orderBy('time_slot')
                ->get()
                ->keyBy('time_slot');
        }

        // データを時間枠にマッピング
        $counts = [];
        foreach ($timeSlots as $slot) {
            $counts[] = isset($accessData[$slot]) ? (int)$accessData[$slot]->count : 0;
        }

        return [
            'labels' => $labels,
            'data' => $counts,
        ];
    }
    
    /**
     * 指定期間の売上統計を取得（通貨別）
     * 
     * @param string $period '1day', '1week', '2weeks', '1month', '6months', '1year', 'all'
     */
    private function selectRevenueStats($period = '1month')
    {
        $now = now();
        
        // 期間に応じて開始時刻と集計間隔を設定
        switch ($period) {
            case '1day':
                $startTime = $now->copy()->subHours(24);
                $interval = 30; // 30分
                $format = 'H:i';
                $groupBy = '30min';
                break;
            case '1week':
                $startTime = $now->copy()->subWeek();
                $interval = 120; // 2時間
                $format = 'm/d H:i';
                $groupBy = '2hour';
                break;
            case '2weeks':
                $startTime = $now->copy()->subWeeks(2);
                $interval = 240; // 4時間
                $format = 'm/d H:i';
                $groupBy = '4hour';
                break;
            case '6months':
                $startTime = $now->copy()->subMonths(6);
                $interval = 10080; // 1週間（7日）
                $format = 'Y/m/d';
                $groupBy = 'week';
                break;
            case '1year':
                $startTime = $now->copy()->subYear();
                $interval = 43200; // 1ヶ月（30日）
                $format = 'Y/m';
                $groupBy = 'month';
                break;
            case 'all':
                // 最古のデータから取得
                $oldestRecord = DB::connection('log')
                    ->table('log_in_app_purchase')
                    ->orderBy('system_at', 'asc')
                    ->first();
                $startTime = $oldestRecord ? \Carbon\Carbon::parse($oldestRecord->system_at) : $now->copy()->subYear();
                $interval = 43200; // 1ヶ月（30日）
                $format = 'Y/m';
                $groupBy = 'month';
                break;
            case '1month':
            default:
                $startTime = $now->copy()->subMonth();
                $interval = 1440; // 1日
                $format = 'm/d';
                $groupBy = 'day';
                break;
        }

        // 時間を適切な単位に丸める
        if ($period === '1day') {
            $minute = (int) $startTime->format('i');
            if ($minute < 30) {
                $startTime->minute(0)->second(0);
            } else {
                $startTime->minute(30)->second(0);
            }
        } elseif (in_array($period, ['1month', '6months', '1year', 'all'])) {
            $startTime->startOfDay();
        } else {
            $startTime->minute(0)->second(0);
            $hour = (int) $startTime->format('H');
            if ($period === '1week') {
                $startTime->hour(intval($hour / 2) * 2);
            } elseif ($period === '2weeks') {
                $startTime->hour(intval($hour / 4) * 4);
            }
        }

        // 時間枠を生成
        $timeSlots = [];
        $labels = [];
        $current = $startTime->copy();
        
        while ($current <= $now) {
            if (in_array($period, ['1month', '6months'])) {
                // 日単位
                $timeSlots[] = $current->format('Y-m-d 00:00:00');
                $labels[] = $current->format($format);
            } elseif (in_array($period, ['1year', 'all'])) {
                // 月単位
                $timeSlots[] = $current->format('Y-m-01 00:00:00');
                $labels[] = $current->format($format);
            } elseif ($period === '1day') {
                // 30分単位
                $timeSlots[] = $current->format('Y-m-d H:i:00');
                $labels[] = $current->format($format);
            } else {
                // 時間単位（1week, 2weeks）
                $timeSlots[] = $current->format('Y-m-d H:00:00');
                $labels[] = $current->format($format);
            }
            $current->addMinutes($interval);
        }

        // 通貨コードの取得
        $currencies = DB::connection('log')
            ->table('log_in_app_purchase')
            ->where('status', 'Purchased') // 購入完了のみ
            ->where('system_at', '>=', $startTime)
            ->where('system_at', '<=', $now)
            ->distinct()
            ->pluck('currency_code')
            ->toArray();

        // 通貨別のデータセットを作成
        $datasets = [];
        
        foreach ($currencies as $currency) {
            // log_in_app_purchaseテーブルから通貨別に集計
            if (in_array($period, ['1month', '6months'])) {
                // 1ヶ月・半年: 日単位で集計
                $revenueData = DB::connection('log')
                    ->table('log_in_app_purchase')
                    ->selectRaw("
                        DATE_FORMAT(system_at, '%Y-%m-%d 00:00:00') as time_slot,
                        SUM(pay_amount) as total_amount
                    ")
                    ->where('status', 'Purchased')
                    ->where('currency_code', $currency)
                    ->where('system_at', '>=', $startTime)
                    ->where('system_at', '<=', $now)
                    ->groupBy('time_slot')
                    ->orderBy('time_slot')
                    ->get()
                    ->keyBy('time_slot');
            } elseif (in_array($period, ['1year', 'all'])) {
                // 1年・通年: 月単位で集計
                $revenueData = DB::connection('log')
                    ->table('log_in_app_purchase')
                    ->selectRaw("
                        DATE_FORMAT(system_at, '%Y-%m-01 00:00:00') as time_slot,
                        SUM(pay_amount) as total_amount
                    ")
                    ->where('status', 'Purchased')
                    ->where('currency_code', $currency)
                    ->where('system_at', '>=', $startTime)
                    ->where('system_at', '<=', $now)
                    ->groupBy('time_slot')
                    ->orderBy('time_slot')
                    ->get()
                    ->keyBy('time_slot');
            } elseif ($period === '1day') {
                // 1日: 30分単位で集計
                $revenueData = DB::connection('log')
                    ->table('log_in_app_purchase')
                    ->selectRaw("
                        CONCAT(
                            DATE_FORMAT(system_at, '%Y-%m-%d %H:'),
                            CASE 
                                WHEN MINUTE(system_at) < 30 THEN '00:00'
                                ELSE '30:00'
                            END
                        ) as time_slot,
                        SUM(pay_amount) as total_amount
                    ")
                    ->where('status', 'Purchased')
                    ->where('currency_code', $currency)
                    ->where('system_at', '>=', $startTime)
                    ->where('system_at', '<=', $now)
                    ->groupBy('time_slot')
                    ->orderBy('time_slot')
                    ->get()
                    ->keyBy('time_slot');
            } else {
                // 1週間、2週間: 2時間または4時間単位で集計
                $hours = $period === '1week' ? 2 : 4;
                $revenueData = DB::connection('log')
                    ->table('log_in_app_purchase')
                    ->selectRaw("
                        CONCAT(
                            DATE_FORMAT(system_at, '%Y-%m-%d '),
                            LPAD((HOUR(system_at) DIV {$hours}) * {$hours}, 2, '0'),
                            ':00:00'
                        ) as time_slot,
                        SUM(pay_amount) as total_amount
                    ")
                    ->where('status', 'Purchased')
                    ->where('currency_code', $currency)
                    ->where('system_at', '>=', $startTime)
                    ->where('system_at', '<=', $now)
                    ->groupBy('time_slot')
                    ->orderBy('time_slot')
                    ->get()
                    ->keyBy('time_slot');
            }

            // データを時間枠にマッピング
            $amounts = [];
            foreach ($timeSlots as $slot) {
                $amounts[] = isset($revenueData[$slot]) ? (float)$revenueData[$slot]->total_amount : 0;
            }

            $datasets[] = [
                'currency' => $currency,
                'data' => $amounts,
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }
}
