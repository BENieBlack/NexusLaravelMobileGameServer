<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TrxPlayerSeeder extends Seeder
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
        // sys_playerテーブルから全プレイヤーを取得
        $players = DB::connection('sys')->table('sys_player')->get();

        if ($players->isEmpty()) {
            $this->command->warn('⚠️  TrxPlayerSeeder: No players found. Run SysPlayerSeeder first.');
            return;
        }

        // sys_sharding_node_playerからプレイヤーのシャード割り当てを取得
        $playerShardMap = DB::connection('sys')
            ->table('sys_sharding_node_player')
            ->get()
            ->keyBy('sys_player_id');

        foreach ($this->connections as $connection) {
            // べき等性を確保するため、既存データを削除
            DB::connection($connection)->table('trx_player_sns')->truncate();
            DB::connection($connection)->table('trx_player')->truncate();

            $this->command->info("Processing connection: {$connection}");
        }

        // シャード番号を取得
        $shardNodes = DB::connection('sys')
            ->table('sys_sharding_node')
            ->pluck('node_no', 'id');

        $createdCounts = [
            'trx1' => 0,
            'trx2' => 0,
        ];

        foreach ($players as $player) {
            // プレイヤーの割り当て先シャードを特定
            $shardAssignment = $playerShardMap[$player->id] ?? null;

            if (!$shardAssignment) {
                $this->command->warn("⚠️  Player {$player->id} has no shard assignment. Skipping.");
                continue;
            }

            $nodeNo = $shardNodes[$shardAssignment->sys_sharding_node_id] ?? null;
            
            if (!$nodeNo) {
                $this->command->warn("⚠️  Cannot find node_no for player {$player->id}. Skipping.");
                continue;
            }

            $connection = "trx{$nodeNo}";

            // trx_playerレコードを作成
            DB::connection($connection)->table('trx_player')->insert([
                'sys_player_id' => $player->id,
                'created_at' => $player->created_at,
                'updated_at' => $player->updated_at,
            ]);

            // 一部のプレイヤーにSNS連携情報を作成
            if (rand(0, 1) === 1) {
                $snsTypes = ['apple', 'google', 'x', 'facebook'];
                $selectedSns = $snsTypes[array_rand($snsTypes)];

                DB::connection($connection)->table('trx_player_sns')->insert([
                    'sys_player_id' => $player->id,
                    'sns_type' => $selectedSns,
                    'sns_user_id' => 'sns_' . $player->id . '_' . $selectedSns,
                    'auth' => hash('sha256', 'auth_' . $player->id),
                    'created_at' => now()->subDays(rand(1, 20)),
                    'updated_at' => now()->subDays(rand(0, 10)),
                ]);
            }

            $createdCounts[$connection]++;
        }

        $this->command->info('✅ TrxPlayerSeeder: Created player transaction data');
        $this->command->info('   - trx1: ' . $createdCounts['trx1'] . ' players');
        $this->command->info('   - trx2: ' . $createdCounts['trx2'] . ' players');
    }
}
