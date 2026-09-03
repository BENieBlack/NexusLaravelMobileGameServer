<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\Mst\_BaseMst;

/**
 * MstLoginBonusSeeder
 *
 * 通常ログインボーナスのマスターデータを生成
 * 7日サイクルのデイリーログインボーナス
 *
 * mst_login_bonus: 1行 = 1日分のボーナス設定（day=1〜7）
 * mst_login_bonus_content: 各日の報酬内容
 */
class MstLoginBonusSeeder extends Seeder
{
    private const LOOP_DAYS = 7;

    public function run(): void
    {
        _BaseMst::allowWrites();

        DB::connection('mst')->statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::connection('mst')->table('mst_login_bonus_content')->truncate();
        DB::connection('mst')->table('mst_login_bonus')->truncate();
        DB::connection('mst')->statement('SET FOREIGN_KEY_CHECKS=1;');

        $now = now();

        // 各日のボーナス設定と報酬
        // content_type: DBのenum値('wallet','item','diamond'等)を使用
        // wallet の場合、サービス層で content_mst_id (gold/coin等) をResourceTypeとして扱う
        $dayRewards = [
            1 => [['content_type' => 'wallet',  'content_mst_id' => 'gold',         'amount' => 1000]],
            2 => [['content_type' => 'wallet',  'content_mst_id' => 'gold',         'amount' => 2000]],
            3 => [['content_type' => 'item',    'content_mst_id' => 'item_001',     'amount' => 1]],
            4 => [['content_type' => 'wallet',  'content_mst_id' => 'gold',         'amount' => 3000]],
            5 => [['content_type' => 'item',    'content_mst_id' => 'item_001',     'amount' => 2]],
            6 => [['content_type' => 'wallet',  'content_mst_id' => 'gold',         'amount' => 5000]],
            7 => [
                ['content_type' => 'diamond',   'content_mst_id' => 'free_diamond', 'amount' => 10],
                ['content_type' => 'wallet',    'content_mst_id' => 'gold',         'amount' => 10000],
            ],
        ];

        for ($day = 1; $day <= self::LOOP_DAYS; $day++) {
            $bonusId = "daily_login_day{$day}";

            DB::connection('mst')->table('mst_login_bonus')->insert([
                'id'                   => $bonusId,
                'type'                 => 'daily',
                'day'                  => $day,
                'loop_days'            => self::LOOP_DAYS,
                'required_absent_days' => null,
                'valid_days'           => null,
                'priority'             => 0,
                'is_active'            => true,
                'start_at'             => null,
                'end_at'               => null,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            foreach ($dayRewards[$day] as $i => $reward) {
                DB::connection('mst')->table('mst_login_bonus_content')->insert([
                    'mst_login_bonus_id' => $bonusId,
                    'content_type'       => $reward['content_type'],
                    'content_mst_id'     => $reward['content_mst_id'],
                    'content_option'     => null,
                    'content_quantity'   => $reward['amount'],
                    'amount'             => $reward['amount'],
                    'is_paid'            => false,
                    'sort_order'         => $i,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]);
            }
        }

        $this->command->info('通常ログインボーナス（7日分）のマスターデータを作成しました。');
    }
}
