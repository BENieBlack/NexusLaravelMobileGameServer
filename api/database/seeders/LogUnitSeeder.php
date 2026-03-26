<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LogUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // べき等性を確保するため、既存データを削除
        DB::connection('log')->table('log_unit')->truncate();

        // log_accessからユニット関連のエンドポイントのunique_request_idを取得
        $unitEndpoints = [
            '/api/quest/complete', // クエストでユニット経験値獲得
            '/api/battle/result', // バトルでユニット経験値獲得
        ];

        $accessLogs = DB::connection('log')
            ->table('log_access')
            ->whereIn('endpoint', $unitEndpoints)
            ->select('unique_request_id', 'sys_player_id', 'system_at', 'created_at')
            ->limit(250) // サンプルとして250件に限定
            ->get();

        if ($accessLogs->isEmpty()) {
            $this->command->warn('⚠️  LogUnitSeeder: No matching access logs found. Run LogAccessSeeder first.');
            return;
        }

        // trx_unitからユニットデータを取得（シャーディング対応）
        $connections = ['trx1', 'trx2'];
        $units = [];
        
        foreach ($connections as $connection) {
            $shardUnits = DB::connection($connection)
                ->table('trx_unit')
                ->select('id', 'sys_player_id', 'mst_unit_id')
                ->get();
            
            foreach ($shardUnits as $unit) {
                if (!isset($units[$unit->sys_player_id])) {
                    $units[$unit->sys_player_id] = [];
                }
                $units[$unit->sys_player_id][] = [
                    'id' => $unit->id,
                    'mst_unit_id' => $unit->mst_unit_id,
                ];
            }
        }

        if (empty($units)) {
            $this->command->warn('⚠️  LogUnitSeeder: No units found. Run TrxUnitSeeder first.');
            return;
        }

        $logCount = 0;
        
        // log_accessのunique_request_idを使用してログを作成
        foreach ($accessLogs as $accessLog) {
            // プレイヤーのユニットリストを取得
            if (!isset($units[$accessLog->sys_player_id]) || empty($units[$accessLog->sys_player_id])) {
                continue;
            }
            
            $playerUnits = $units[$accessLog->sys_player_id];
            $unit = $playerUnits[array_rand($playerUnits)];
            
            $beforeGrade = rand(1, 10);
            $afterGrade = $beforeGrade + rand(0, 2);
            $beforeLevel = rand(1, 99);
            $afterLevel = $beforeLevel + rand(1, 5);

            DB::connection('log')->table('log_unit')->insert([
                'unique_request_id' => $accessLog->unique_request_id,
                'sys_player_id' => $accessLog->sys_player_id,
                'trx_unit_id' => $unit['id'],
                'mst_unit_id' => $unit['mst_unit_id'],
                'before_grade' => $beforeGrade,
                'after_grade' => $afterGrade,
                'before_level' => $beforeLevel,
                'after_level' => $afterLevel,
                'system_at' => $accessLog->system_at,
                'created_at' => $accessLog->created_at,
            ]);

            $logCount++;
        }

        $this->command->info("✅ LogUnitSeeder: Created {$logCount} unit change logs");
    }
}
