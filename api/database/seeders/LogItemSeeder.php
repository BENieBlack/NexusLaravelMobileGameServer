<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LogItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // べき等性を確保するため、既存データを削除
        DB::connection('log')->table('log_item')->truncate();

        // log_accessからアイテム関連のエンドポイントのunique_request_idを取得
        $itemEndpoints = [
            '/api/item/use',
            '/api/quest/complete', // クエスト報酬
            '/api/shop/purchase',
            '/api/mission/complete', // ミッション報酬
        ];

        $accessLogs = DB::connection('log')
            ->table('log_access')
            ->whereIn('endpoint', $itemEndpoints)
            ->select('unique_request_id', 'sys_player_id', 'system_at', 'created_at')
            ->limit(300) // サンプルとして300件に限定
            ->get();

        if ($accessLogs->isEmpty()) {
            $this->command->warn('⚠️  LogItemSeeder: No matching access logs found. Run LogAccessSeeder first.');
            return;
        }

        // マスターアイテムを取得
        $masterItems = DB::connection('mst')->table('mst_item')->pluck('id')->toArray();

        if (empty($masterItems)) {
            $this->command->warn('⚠️  LogItemSeeder: No master items found. Run MstItemSeeder first.');
            return;
        }

        $logCount = 0;
        
        // log_accessのunique_request_idを使用してログを作成
        foreach ($accessLogs as $accessLog) {
            $mstItemId = $masterItems[array_rand($masterItems)];
            $beforeAmount = rand(0, 500);
            $changeAmount = rand(-50, 100);
            $afterAmount = max(0, $beforeAmount + $changeAmount);

            DB::connection('log')->table('log_item')->insert([
                'unique_request_id' => $accessLog->unique_request_id,
                'sys_player_id' => $accessLog->sys_player_id,
                'mst_item_id' => $mstItemId,
                'before_amount' => $beforeAmount,
                'after_amount' => $afterAmount,
                'system_at' => $accessLog->system_at,
                'created_at' => $accessLog->created_at,
            ]);

            $logCount++;
        }

        $this->command->info("✅ LogItemSeeder: Created {$logCount} item change logs");
    }
}
