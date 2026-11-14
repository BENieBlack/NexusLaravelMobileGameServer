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
 * mst_login_bonus: ログインボーナスの種類とループ設定
 * mst_login_bonus_content: 各日の報酬内容（day=1〜7）
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
        // content_type: DBのenum値('wallet','item'等)を使用
        // 無償ダイヤは content_type=item・content_mst_id=free_diamond（trx_item に加算される。
        // 6 日目は以前 diamond / diamond と混在していたが free_diamond に統一。量は変えない）
        // 5 日目のスタミナは stamina_50（mst_item。使うと 50 回復）を 1 個渡す。
        // スタミナ行に依存せず trx_item への加算だけで済む
        // wallet の場合、サービス層で content_mst_id (gold/coin等) をResourceTypeとして扱う
        $dayRewards = [
            1 => [['content_type' => 'item',    'content_mst_id' => 'free_diamond', 'amount' => 100]],
            2 => [['content_type' => 'wallet',  'content_mst_id' => 'gold',         'amount' => 5000]],
            3 => [['content_type' => 'item',    'content_mst_id' => 'free_diamond', 'amount' => 100]],
            4 => [['content_type' => 'wallet',  'content_mst_id' => 'gold',         'amount' => 10000]],
            5 => [['content_type' => 'item',    'content_mst_id' => 'stamina_50',   'amount' => 1]],
            6 => [['content_type' => 'item',    'content_mst_id' => 'free_diamond', 'amount' => 20000]],
            7 => [['content_type' => 'item',    'content_mst_id' => 'free_diamond', 'amount' => 500]],
        ];

        $bonusId = 'daily_login';

        DB::connection('mst')->table('mst_login_bonus')->insert([
            'id' => $bonusId,
            'type' => 'daily',
            'loop_days' => self::LOOP_DAYS,
            'required_absent_days' => null,
            'valid_days' => null,
            'priority' => 0,
            'is_active' => true,
            'start_at' => null,
            'end_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        for ($day = 1; $day <= self::LOOP_DAYS; $day++) {
            foreach ($dayRewards[$day] as $i => $reward) {
                DB::connection('mst')->table('mst_login_bonus_content')->insert([
                    'mst_login_bonus_id' => $bonusId,
                    'day' => $day,
                    'content_type' => $reward['content_type'],
                    'content_mst_id' => $reward['content_mst_id'],
                    'content_option' => null,
                    'content_quantity' => $reward['amount'],
                    // amount は倍率（配布量 = content_quantity × amount）。配布量は content_quantity に入れるので 1
                    'amount' => 1,
                    'is_paid' => false,
                    'sort_order' => $i,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->command->info('通常ログインボーナス（7日分）のマスターデータを作成しました。');
    }
}
