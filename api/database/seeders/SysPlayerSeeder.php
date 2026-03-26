<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SysPlayerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // べき等性を確保するため、既存データを削除
        // 外部キー制約を一時的に無効化してtruncate
        DB::connection('sys')->statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::connection('sys')->table('sys_player_token')->truncate();
        DB::connection('sys')->table('sys_player_device')->truncate();
        DB::connection('sys')->table('sys_sharding_node_player')->truncate();
        DB::connection('sys')->table('sys_player')->truncate();
        DB::connection('sys')->statement('SET FOREIGN_KEY_CHECKS=1;');
        
        // シャーディングノードを取得
        $shardingNodes = DB::connection('sys')->table('sys_sharding_node')
            ->orderBy('node_no')
            ->get();
        
        if ($shardingNodes->count() === 0) {
            $this->command->warn('⚠️  No sharding nodes found. Run SysShardingSeeder first.');
            return;
        }

        // テストプレイヤーを10人作成
        $players = [];
        for ($i = 1; $i <= 10; $i++) {
            $playerId = DB::connection('sys')->table('sys_player')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'my_id' => strtoupper(Str::random(8)),
                'name' => "TestPlayer{$i}",
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now()->subDays(rand(0, 10)),
            ]);

            $players[] = $playerId;
            
            // プレイヤーをシャードに割り当て（ラウンドロビン方式）
            $nodeIndex = ($i - 1) % $shardingNodes->count();
            $assignedNode = $shardingNodes[$nodeIndex];
            
            DB::connection('sys')->table('sys_sharding_node_player')->insert([
                'sys_sharding_node_id' => $assignedNode->id,
                'sys_player_id' => $playerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // ノードのプレイヤー数を更新
            DB::connection('sys')->table('sys_sharding_node')
                ->where('id', $assignedNode->id)
                ->increment('current_player_count');

            // 各プレイヤーに1〜2個のデバイスを作成
            $deviceCount = rand(1, 2);
            for ($d = 1; $d <= $deviceCount; $d++) {
                $deviceId = DB::connection('sys')->table('sys_player_device')->insertGetId([
                    'sys_player_id' => $playerId,
                    'uuid' => (string) Str::uuid(),
                    'device_info' => json_encode([
                        'os' => ['iOS', 'Android'][rand(0, 1)],
                        'os_version' => rand(13, 17) . '.' . rand(0, 5),
                        'model' => ['iPhone 14 Pro', 'iPhone 15', 'Pixel 7', 'Galaxy S23'][rand(0, 3)],
                        'app_version' => '1.0.' . rand(0, 10),
                    ]),
                    'last_login_at' => now()->subHours(rand(1, 72)),
                    'created_at' => now()->subDays(rand(1, 30)),
                    'updated_at' => now()->subHours(rand(1, 72)),
                ]);

                // 各デバイスにリフレッシュトークンを作成（有効なもの）
                DB::connection('sys')->table('sys_player_token')->insert([
                    'sys_player_id' => $playerId,
                    'sys_player_device_id' => $deviceId,
                    'refresh_token_hash' => hash('sha256', Str::random(64)),
                    'expires_at' => now()->addDays(30),
                    'revoked_at' => null,
                    'created_at' => now()->subDays(rand(1, 10)),
                    'updated_at' => now()->subDays(rand(1, 10)),
                ]);

                // 一部のデバイスには無効化されたトークンも作成
                if (rand(0, 1) === 1) {
                    DB::connection('sys')->table('sys_player_token')->insert([
                        'sys_player_id' => $playerId,
                        'sys_player_device_id' => $deviceId,
                        'refresh_token_hash' => hash('sha256', Str::random(64)),
                        'expires_at' => now()->addDays(30),
                        'revoked_at' => now()->subDays(rand(1, 5)),
                        'created_at' => now()->subDays(rand(11, 20)),
                        'updated_at' => now()->subDays(rand(1, 5)),
                    ]);
                }
            }
        }

        $this->command->info('✅ SysPlayerSeeder: 10 players created with devices and tokens');
        $this->command->info('   - Player IDs: ' . implode(', ', $players));
    }
}
