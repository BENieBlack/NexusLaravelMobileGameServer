<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SysFriendApplySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // べき等性を確保するため、既存データを削除
        DB::connection('sys')->table('sys_friend_apply')->truncate();

        // sys_playerテーブルから全プレイヤーを取得
        $players = DB::connection('sys')->table('sys_player')->pluck('id')->toArray();

        if (count($players) < 2) {
            $this->command->warn('⚠️  SysFriendApplySeeder: Not enough players. Run SysPlayerSeeder first.');

            return;
        }

        // フレンド申請を作成
        $friendApplies = [];

        // プレイヤー1がプレイヤー2-4に申請を送信
        for ($i = 1; $i < min(4, count($players)); $i++) {
            $friendApplies[] = [
                'sender_sys_player_id' => $players[0],
                'receiver_sys_player_id' => $players[$i],
                'status' => 'accepted',
                'created_at' => now()->subDays(rand(5, 20)),
                'updated_at' => now()->subDays(rand(1, 5)),
            ];
        }

        // プレイヤー3がプレイヤー5に申請（承認済み）
        if (count($players) >= 5) {
            $friendApplies[] = [
                'sender_sys_player_id' => $players[2],
                'receiver_sys_player_id' => $players[4],
                'status' => 'accepted',
                'created_at' => now()->subDays(rand(3, 10)),
                'updated_at' => now()->subDays(rand(1, 3)),
            ];
        }

        // プレイヤー5がプレイヤー1に申請（未承認）
        if (count($players) >= 5) {
            $friendApplies[] = [
                'sender_sys_player_id' => $players[4],
                'receiver_sys_player_id' => $players[0],
                'status' => 'applied',
                'created_at' => now()->subDays(rand(1, 3)),
                'updated_at' => now()->subDays(rand(1, 3)),
            ];
        }

        // プレイヤー6がプレイヤー2に申請（未承認）
        if (count($players) >= 6) {
            $friendApplies[] = [
                'sender_sys_player_id' => $players[5],
                'receiver_sys_player_id' => $players[1],
                'status' => 'applied',
                'created_at' => now()->subHours(rand(1, 24)),
                'updated_at' => now()->subHours(rand(1, 24)),
            ];
        }

        // プレイヤー2がプレイヤー7に申請（削除済み）
        if (count($players) >= 7) {
            $friendApplies[] = [
                'sender_sys_player_id' => $players[1],
                'receiver_sys_player_id' => $players[6],
                'status' => 'deleted',
                'created_at' => now()->subDays(rand(10, 20)),
                'updated_at' => now()->subDays(rand(1, 5)),
            ];
        }

        DB::connection('sys')->table('sys_friend_apply')->insert($friendApplies);

        $this->command->info('✅ SysFriendApplySeeder: Created '.count($friendApplies).' friend applies');
        $this->command->info('   - Accepted: '.collect($friendApplies)->where('status', 'accepted')->count());
        $this->command->info('   - Applied: '.collect($friendApplies)->where('status', 'applied')->count());
        $this->command->info('   - Deleted: '.collect($friendApplies)->where('status', 'deleted')->count());
    }
}
