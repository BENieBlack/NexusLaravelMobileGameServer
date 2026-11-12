<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\UsesShardConnections;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TrxItemSeeder extends Seeder
{
    // 接続名は DB_SHARD_COUNT に従う全シャード（trx1, trx2 のハードコードをやめた）
    use UsesShardConnections;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // マスターアイテムを取得
        $masterItems = DB::connection('mst')->table('mst_item')->pluck('id')->toArray();

        if (empty($masterItems)) {
            $this->command->warn('⚠️  TrxItemSeeder: No master items found. Run MstItemSeeder first.');

            return;
        }

        // sys_playerテーブルから全プレイヤーを取得
        $players = DB::connection('sys')->table('sys_player')->get();

        if ($players->isEmpty()) {
            $this->command->warn('⚠️  TrxItemSeeder: No players found. Run SysPlayerSeeder first.');

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

        $connections = $this->shardConnections();

        foreach ($connections as $connection) {
            // べき等性を確保するため、既存データを削除
            DB::connection($connection)->table('trx_item')->truncate();
            $this->command->info("Truncated trx_item on {$connection}");
        }

        $createdCounts = array_fill_keys($connections, 0);

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

            // 各プレイヤーに5〜10種類のアイテムを付与
            $itemCount = rand(5, 10);
            $selectedItems = array_rand(array_flip($masterItems), $itemCount);

            if (! is_array($selectedItems)) {
                $selectedItems = [$selectedItems];
            }

            foreach ($selectedItems as $mstItemId) {
                $totalAmount = rand(1, 999);
                $paidRatio = rand(0, 30) / 100; // 0-30%が有償
                $paidAmount = (int) ($totalAmount * $paidRatio);
                $freeAmount = $totalAmount - $paidAmount;

                DB::connection($connection)->table('trx_item')->insert([
                    'sys_player_id' => $player->id,
                    'mst_item_id' => $mstItemId,
                    'free_amount' => $freeAmount,
                    'paid_amount' => $paidAmount,
                    'created_at' => now()->subDays(rand(1, 30)),
                    'updated_at' => now()->subDays(rand(0, 10)),
                ]);

                $createdCounts[$connection]++;
            }
        }

        $this->command->info('✅ TrxItemSeeder: Created item ownership data');
        foreach ($createdCounts as $connection => $count) {
            $this->command->info("   - {$connection}: {$count} items");
        }
    }
}
