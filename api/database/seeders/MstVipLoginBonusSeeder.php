<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MstVipLoginBonusSeeder
 * 
 * VIPレベル0〜10のログインボーナス設定データを生成
 * 
 * VIPレベルが高いほど報酬が豪華になる設計
 * - VIP0: ゴールド中心
 * - VIP5: ゴールド増量 + アイテム
 * - VIP10: ゴールド大量 + ダイヤモンド + レアアイテム
 */
class MstVipLoginBonusSeeder extends Seeder
{
    /**
     * VIPレベルの最大値
     */
    private const MAX_VIP_LEVEL = 10;

    /**
     * ログインボーナスのループ日数
     */
    private const LOOP_DAYS = 7;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 外部キー制約を一時的に無効化
        DB::connection('mst')->statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // 既存データをクリア
        DB::connection('mst')->table('mst_vip_login_bonus_content')->truncate();
        DB::connection('mst')->table('mst_vip_login_bonus')->truncate();
        
        // 外部キー制約を再度有効化
        DB::connection('mst')->statement('SET FOREIGN_KEY_CHECKS=1;');

        $now = now();

        for ($vipLevel = 0; $vipLevel <= self::MAX_VIP_LEVEL; $vipLevel++) {
            $bonusId = "vip_login_lv{$vipLevel}";

            // VIPログインボーナス設定を作成
            DB::connection('mst')->table('mst_vip_login_bonus')->insert([
                'id' => $bonusId,
                'vip_level' => $vipLevel,
                'loop_days' => self::LOOP_DAYS,
                'is_active' => true,
                'start_at' => null,
                'end_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 日別報酬を作成
            $contents = $this->generateContentsForVipLevel($bonusId, $vipLevel, $now);
            DB::connection('mst')->table('mst_vip_login_bonus_content')->insert($contents);

            $this->command->info("VIPレベル{$vipLevel}のログインボーナスを作成しました。");
        }

        $this->command->info('VIPログインボーナスのマスターデータ作成が完了しました。');
    }

    /**
     * VIPレベルに応じた日別報酬内容を生成
     *
     * @param string $bonusId VIPログインボーナスID
     * @param int $vipLevel VIPレベル
     * @param \Carbon\Carbon $now 現在日時
     * @return array 報酬内容の配列
     */
    private function generateContentsForVipLevel(string $bonusId, int $vipLevel, $now): array
    {
        $contents = [];

        // VIPレベルに応じた基本報酬倍率（VIP0=1.0, VIP5=2.0, VIP10=4.0）
        $multiplier = 1 + ($vipLevel * 0.3);

        for ($day = 1; $day <= self::LOOP_DAYS; $day++) {
            // 1〜6日目: ゴールド報酬
            if ($day <= 6) {
                $goldAmount = (int) floor(1000 * $day * $multiplier);
                
                $contents[] = [
                    'mst_vip_login_bonus_id' => $bonusId,
                    'day' => $day,
                    'content_type' => 'currency',
                    'content_id' => 'gold',
                    'content_option' => null,
                    'content_quantity' => $goldAmount,
                    'amount' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // 7日目: ダイヤモンド報酬（VIPレベルに応じて増量）
            if ($day === 7) {
                // ゴールド（大量）
                $goldAmount = (int) floor(10000 * $multiplier);
                $contents[] = [
                    'mst_vip_login_bonus_id' => $bonusId,
                    'day' => $day,
                    'content_type' => 'currency',
                    'content_id' => 'gold',
                    'content_option' => null,
                    'content_quantity' => $goldAmount,
                    'amount' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // ダイヤモンド
                $diamondAmount = (int) floor(10 * $multiplier);
                $contents[] = [
                    'mst_vip_login_bonus_id' => $bonusId,
                    'day' => $day,
                    'content_type' => 'diamond',
                    'content_id' => 'free_diamond',
                    'content_option' => null,
                    'content_quantity' => $diamondAmount,
                    'amount' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // VIP5以上: 4日目にアイテム追加
            if ($vipLevel >= 5 && $day === 4) {
                $contents[] = [
                    'mst_vip_login_bonus_id' => $bonusId,
                    'day' => $day,
                    'content_type' => 'item',
                    'content_id' => 'item_exp_potion_large',
                    'content_option' => null,
                    'content_quantity' => (int) floor(5 * ($vipLevel / 5)),
                    'amount' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // VIP10: 7日目にレアアイテム追加
            if ($vipLevel >= 10 && $day === 7) {
                $contents[] = [
                    'mst_vip_login_bonus_id' => $bonusId,
                    'day' => $day,
                    'content_type' => 'item',
                    'content_id' => 'item_gacha_ticket_rare',
                    'content_option' => null,
                    'content_quantity' => 1,
                    'amount' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $contents;
    }
}
