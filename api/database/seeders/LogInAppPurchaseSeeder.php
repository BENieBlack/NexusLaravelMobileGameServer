<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LogInAppPurchaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $connection = 'log';

        // 既存のデータを削除
        DB::connection($connection)->table('log_in_app_purchase')->truncate();

        // log_accessから課金関連のエンドポイントのunique_request_idを取得
        $iapEndpoints = [
            '/api/shop/purchase',
        ];

        $accessLogs = DB::connection('log')
            ->table('log_access')
            ->whereIn('endpoint', $iapEndpoints)
            ->select('unique_request_id', 'sys_player_id', 'system_at', 'created_at')
            ->get();

        if ($accessLogs->isEmpty()) {
            $this->command->warn('⚠️  LogInAppPurchaseSeeder: No matching access logs found. Run LogAccessSeeder first.');

            return;
        }

        // 課金パッケージ定義
        $packages = [
            // JPY (日本円)
            ['id' => 'gem_pack_120', 'currency' => 'JPY', 'amount' => 120, 'string' => '¥120'],
            ['id' => 'gem_pack_250', 'currency' => 'JPY', 'amount' => 250, 'string' => '¥250'],
            ['id' => 'gem_pack_490', 'currency' => 'JPY', 'amount' => 490, 'string' => '¥490'],
            ['id' => 'gem_pack_980', 'currency' => 'JPY', 'amount' => 980, 'string' => '¥980'],
            ['id' => 'gem_pack_1980', 'currency' => 'JPY', 'amount' => 1980, 'string' => '¥1,980'],
            ['id' => 'gem_pack_4900', 'currency' => 'JPY', 'amount' => 4900, 'string' => '¥4,900'],
            ['id' => 'gem_pack_9800', 'currency' => 'JPY', 'amount' => 9800, 'string' => '¥9,800'],

            // USD (米ドル)
            ['id' => 'gem_pack_099', 'currency' => 'USD', 'amount' => 0.99, 'string' => '$0.99'],
            ['id' => 'gem_pack_199', 'currency' => 'USD', 'amount' => 1.99, 'string' => '$1.99'],
            ['id' => 'gem_pack_499', 'currency' => 'USD', 'amount' => 4.99, 'string' => '$4.99'],
            ['id' => 'gem_pack_999', 'currency' => 'USD', 'amount' => 9.99, 'string' => '$9.99'],
            ['id' => 'gem_pack_1999', 'currency' => 'USD', 'amount' => 19.99, 'string' => '$19.99'],
            ['id' => 'gem_pack_4999', 'currency' => 'USD', 'amount' => 49.99, 'string' => '$49.99'],
            ['id' => 'gem_pack_9999', 'currency' => 'USD', 'amount' => 99.99, 'string' => '$99.99'],

            // EUR (ユーロ)
            ['id' => 'gem_pack_099_eur', 'currency' => 'EUR', 'amount' => 0.99, 'string' => '€0.99'],
            ['id' => 'gem_pack_199_eur', 'currency' => 'EUR', 'amount' => 1.99, 'string' => '€1.99'],
            ['id' => 'gem_pack_499_eur', 'currency' => 'EUR', 'amount' => 4.99, 'string' => '€4.99'],
            ['id' => 'gem_pack_999_eur', 'currency' => 'EUR', 'amount' => 9.99, 'string' => '€9.99'],
            ['id' => 'gem_pack_1999_eur', 'currency' => 'EUR', 'amount' => 19.99, 'string' => '€19.99'],
            ['id' => 'gem_pack_4999_eur', 'currency' => 'EUR', 'amount' => 49.99, 'string' => '€49.99'],
        ];

        $platforms = ['apple', 'google'];
        $billingPlatforms = [
            'apple' => 'app_store',
            'google' => 'google_play',
        ];

        $statuses = [
            'purchased' => 85,  // 85%が購入完了
            'check_availability' => 10,  // 10%が確認中
            'failed' => 3,  // 3%が失敗
            'refunded' => 2,  // 2%が返金
        ];

        echo "アプリ内課金の仮データを生成中...\n";

        $data = [];
        $logCount = 0;

        // log_accessのunique_request_idを使用してログを作成
        foreach ($accessLogs as $accessLog) {
            $package = $packages[array_rand($packages)];
            $platform = $platforms[array_rand($platforms)];

            // ステータスを確率に基づいて選択
            $rand = rand(1, 100);
            $cumulativePercent = 0;
            $selectedStatus = 'purchased';
            foreach ($statuses as $status => $percent) {
                $cumulativePercent += $percent;
                if ($rand <= $cumulativePercent) {
                    $selectedStatus = $status;
                    break;
                }
            }

            // レシートIDを生成
            $receiptId = 'receipt_'.uniqid().'_'.time();

            // レシート情報（JSON）
            $receipt = [
                'transaction_id' => 'txn_'.uniqid(),
                'product_id' => $package['id'],
                'purchase_date' => $accessLog->system_at,
                'quantity' => 1,
            ];

            $data[] = [
                'unique_request_id' => $accessLog->unique_request_id,
                'sys_player_id' => $accessLog->sys_player_id,
                'platform' => $platform,
                'billing_platform' => $billingPlatforms[$platform],
                'receipt_id' => $receiptId,
                'receipt' => json_encode($receipt),
                'status' => $selectedStatus,
                'mst_in_app_purchase_id' => $package['id'],
                'currency_code' => $package['currency'],
                'pay_amount' => $package['amount'],
                'pay_string' => $package['string'],
                'system_at' => $accessLog->system_at,
                'created_at' => $accessLog->created_at,
            ];

            $logCount++;

            // バッチで挿入（500件ごと）
            if (count($data) >= 500) {
                DB::connection($connection)->table('log_in_app_purchase')->insert($data);
                echo '挿入: '.count($data)." 件 (累計: {$logCount} 件)\n";
                $data = [];
            }
        }

        // 残りのデータを挿入
        if (! empty($data)) {
            DB::connection($connection)->table('log_in_app_purchase')->insert($data);
            echo '挿入: '.count($data)." 件 (累計: {$logCount} 件)\n";
        }

        $totalCount = DB::connection($connection)->table('log_in_app_purchase')->count();

        // 通貨別の売上集計
        $revenueByStatus = DB::connection($connection)
            ->table('log_in_app_purchase')
            ->select('currency_code', 'status', DB::raw('SUM(pay_amount) as total_amount'))
            ->groupBy('currency_code', 'status')
            ->get();

        echo "\n完了: 合計 {$totalCount} 件の課金ログを生成しました。\n\n";
        echo "=== ステータス別売上集計 ===\n";
        foreach ($revenueByStatus as $row) {
            echo "{$row->currency_code} - {$row->status}: ".number_format($row->total_amount, 2)."\n";
        }

        $this->command->info("✅ LogInAppPurchaseSeeder: Created {$logCount} in-app purchase logs");
    }
}
