<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TrxUnitSeeder extends Seeder
{
    /**
     * シャーディング対象の接続名
     */
    protected $connections = ['trx1', 'trx2'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // マスターユニットを取得
        $masterUnits = DB::connection('mst')->table('mst_unit')->pluck('id')->toArray();

        if (empty($masterUnits)) {
            $this->command->warn('⚠️  TrxUnitSeeder: No master units found. Run MstUnitSeeder first.');

            return;
        }

        // sys_playerテーブルから全プレイヤーを取得
        $players = DB::connection('sys')->table('sys_player')->get();

        if ($players->isEmpty()) {
            $this->command->warn('⚠️  TrxUnitSeeder: No players found. Run SysPlayerSeeder first.');

            return;
        }

        // sys_sharding_node_playerからプレイヤーのシャード割り当てを取得
        $playerShardMap = DB::connection('sys')
            ->table('sys_sharding_node_player')
            ->get()
            ->keyBy('sys_player_id');

        // シャード番号を取得
        $shardNodes = DB::connection('sys')
            ->table('sys_sharding_node')
            ->pluck('node_no', 'id');

        foreach ($this->connections as $connection) {
            // べき等性を確保するため、既存データを削除
            DB::connection($connection)->table('trx_unit')->truncate();
            $this->command->info("Truncated trx_unit on {$connection}");
        }

        $createdCounts = [
            'trx1' => 0,
            'trx2' => 0,
        ];

        foreach ($players as $player) {
            // プレイヤーの割り当て先シャードを特定
            $shardAssignment = $playerShardMap[$player->id] ?? null;

            if (! $shardAssignment) {
                continue;
            }

            $nodeNo = $shardNodes[$shardAssignment->sys_sharding_node_id] ?? null;

            if (! $nodeNo) {
                continue;
            }

            $connection = "trx{$nodeNo}";

            // 各プレイヤーに3〜8個のユニットを付与
            $unitCount = rand(3, 8);
            for ($i = 0; $i < $unitCount; $i++) {
                $mstUnitId = $masterUnits[array_rand($masterUnits)];

                DB::connection($connection)->table('trx_unit')->insert([
                    'sys_player_id' => $player->id,
                    'mst_unit_id' => $mstUnitId,
                    'grade' => rand(1, 5),
                    'level' => rand(1, 100),
                    'created_at' => now()->subDays(rand(1, 30)),
                    'updated_at' => now()->subDays(rand(0, 10)),
                ]);

                $createdCounts[$connection]++;
            }
        }

        $this->command->info('✅ TrxUnitSeeder: Created unit ownership data');
        $this->command->info('   - trx1: '.$createdCounts['trx1'].' units');
        $this->command->info('   - trx2: '.$createdCounts['trx2'].' units');
    }
}
