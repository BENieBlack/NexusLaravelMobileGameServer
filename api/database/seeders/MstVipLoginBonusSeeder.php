<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\Mst\_BaseMst;
use Nexus\Core\Utilities\ClockUtility;

/**
 * MstVipLoginBonusSeeder
 *
 * VIPレベル0〜10のログインボーナス設定データを生成
 *
 * VIPレベルが高いほど報酬が豪華になる設計
 * - VIP0: ゴールド中心
 * - VIP5: ゴールド増量 + アイテム
 * - VIP10: ゴールド大量 + ダイヤモンド + 上位アイテム
 *
 * 使うマスター ID（すべて実在するもの）:
 * - ゴールド: content_type=wallet・content_mst_id=gold（mst_item の type=currency の行。MstItemSeeder）
 * - ダイヤ: content_type=diamond・content_mst_id=diamond
 * - アイテム: item_exp_002 / item_exp_003（MstItemSeeder。MstItemSeeder を先に流す）
 *
 * content_quantity が配布量、amount は倍率で常に 1（配布量 = content_quantity × amount）。
 * mst_vip_login_bonus* だけを truncate するので、通常ログインボーナス（MstLoginBonusSeeder）とは
 * 互いに影響しない。mst_vip_login_bonus は priority 列を持たない（VIP レベルで 1 件に決まる）。
 * 冪等: truncate して作り直すので何度流しても同じ（DatabaseSeeder の Phase 1 から呼ぶ）。
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
     * 通貨（ゴールド）の content_type。
     * 配布時（_BaseLoginBonusService::convertToResource）は 'wallet' のとき content_mst_id を
     * ResourceType として扱う。'currency' という値は ResourceType に無く ValueError になるので使わない
     */
    public const TYPE_WALLET = 'wallet';

    /** 無償ダイヤの content_type（ResourceType::DIAMOND の値） */
    public const TYPE_DIAMOND = 'diamond';

    /** wallet の ID。mst_item に Wallet 管理の行（type=currency）として実在する（MstItemSeeder） */
    public const GOLD_ID = 'gold';

    /** 無償ダイヤの ID（Resource::diamond() と同じ。ダイヤには mst の行は無い） */
    public const DIAMOND_ID = 'diamond';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // マスターデータの投入なので書き込みを許可する
        _BaseMst::allowWrites();

        // 外部キー制約を一時的に無効化（content が bonus を参照するため、truncate の順序によらず通す）
        DB::connection('mst')->statement('SET FOREIGN_KEY_CHECKS=0;');

        // 既存データをクリア
        DB::connection('mst')->table('mst_vip_login_bonus_content')->truncate();
        DB::connection('mst')->table('mst_vip_login_bonus')->truncate();

        // 外部キー制約を再度有効化
        DB::connection('mst')->statement('SET FOREIGN_KEY_CHECKS=1;');

        $now = ClockUtility::nowToString();

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
     * @param  string  $bonusId  VIPログインボーナスID
     * @param  int  $vipLevel  VIPレベル
     * @param  string  $now  現在日時（'Y-m-d H:i:s'）
     * @return array 報酬内容の配列
     */
    private function generateContentsForVipLevel(string $bonusId, int $vipLevel, string $now): array
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
                    'content_type' => self::TYPE_WALLET,
                    'content_mst_id' => self::GOLD_ID,
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
                    'content_type' => self::TYPE_WALLET,
                    'content_mst_id' => self::GOLD_ID,
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
                    'content_type' => self::TYPE_DIAMOND,
                    'content_mst_id' => self::DIAMOND_ID,
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
                    'content_mst_id' => 'item_exp_002',
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
                    'content_mst_id' => 'item_exp_003',
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
