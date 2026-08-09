<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LogPlayerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // べき等性を確保するため、既存データを削除
        DB::connection('log')->table('log_player')->truncate();

        // log_accessからプレイヤー関連のエンドポイントのunique_request_idを取得
        $playerEndpoints = [
            '/api/player/level-up',
            '/api/quest/complete',
            '/api/battle/result',
            '/api/mission/complete',
        ];

        $accessLogs = DB::connection('log')
            ->table('log_access')
            ->whereIn('endpoint', $playerEndpoints)
            ->select('unique_request_id', 'sys_player_id', 'system_at', 'created_at')
            ->limit(200) // サンプルとして200件に限定
            ->get();

        if ($accessLogs->isEmpty()) {
            $this->command->warn('⚠️  LogPlayerSeeder: No matching access logs found. Run LogAccessSeeder first.');

            return;
        }

        $logCount = 0;

        // log_accessのunique_request_idを使用してログを作成
        foreach ($accessLogs as $accessLog) {
            $beforeLevel = rand(1, 99);
            $afterLevel = $beforeLevel + rand(0, 3);
            $beforeExp = rand(0, 999);
            $afterExp = $afterLevel > $beforeLevel ? rand(0, 300) : $beforeExp + rand(100, 500);

            DB::connection('log')->table('log_player')->insert([
                'unique_request_id' => $accessLog->unique_request_id,
                'sys_player_id' => $accessLog->sys_player_id,
                'before_level' => $beforeLevel,
                'before_level_exp' => $beforeExp,
                'after_level' => $afterLevel,
                'after_level_exp' => $afterExp,
                'system_at' => $accessLog->system_at,
                'created_at' => $accessLog->created_at,
            ]);

            $logCount++;
        }

        $this->command->info("✅ LogPlayerSeeder: Created {$logCount} player change logs");
    }
}
