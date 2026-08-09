<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LogGachaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // べき等性を確保するため、既存データを削除
        DB::connection('log')->table('log_gacha')->truncate();

        // log_accessからガチャ関連のエンドポイントのunique_request_idを取得
        $gachaEndpoints = [
            '/api/gacha/draw',
        ];

        $accessLogs = DB::connection('log')
            ->table('log_access')
            ->whereIn('endpoint', $gachaEndpoints)
            ->select('unique_request_id', 'sys_player_id', 'system_at', 'created_at')
            ->limit(150) // サンプルとして150件に限定
            ->get();

        if ($accessLogs->isEmpty()) {
            $this->command->warn('⚠️  LogGachaSeeder: No matching access logs found. Run LogAccessSeeder first.');

            return;
        }

        // マスターユニットを取得
        $masterUnits = DB::connection('mst')->table('mst_unit')->pluck('id')->toArray();

        if (empty($masterUnits)) {
            $this->command->warn('⚠️  LogGachaSeeder: No master units found. Run MstUnitSeeder first.');

            return;
        }

        $gachaTypes = ['gacha_premium', 'gacha_normal', 'gacha_limited', 'gacha_daily'];

        $logCount = 0;

        // log_accessのunique_request_idを使用してログを作成
        foreach ($accessLogs as $accessLog) {
            // ガチャ結果を生成（1回〜10連）
            $pullCount = [1, 1, 1, 10][array_rand([1, 1, 1, 10])];
            $results = [];

            for ($p = 0; $p < $pullCount; $p++) {
                $results[] = [
                    'unit_id' => $masterUnits[array_rand($masterUnits)],
                    'rarity' => ['C', 'UC', 'R', 'SR', 'SSR', 'UR'][array_rand(['C', 'UC', 'R', 'SR', 'SSR', 'UR'])],
                    'is_new' => rand(0, 1) === 1,
                ];
            }

            DB::connection('log')->table('log_gacha')->insert([
                'unique_request_id' => $accessLog->unique_request_id,
                'sys_player_id' => $accessLog->sys_player_id,
                'mst_gacha_id' => $gachaTypes[array_rand($gachaTypes)],
                'result' => json_encode($results),
                'system_at' => $accessLog->system_at,
                'created_at' => $accessLog->created_at,
            ]);

            $logCount++;
        }

        $this->command->info("✅ LogGachaSeeder: Created {$logCount} gacha logs");
    }
}
