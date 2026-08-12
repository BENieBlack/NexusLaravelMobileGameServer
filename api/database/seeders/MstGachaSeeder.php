<?php

namespace Database\Seeders;

use App\Models\Mst\MstGacha;
use App\Models\Mst\MstGachaCost;
use App\Models\Mst\MstGachaPrize;
use App\Models\Mst\MstGachaRarityRate;
use App\Models\Mst\MstGachaStep;
use App\Models\Mst\MstGachaStepBonus;
use App\Models\Mst\MstGachaStepBonusContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\Mst\_BaseMst;

class MstGachaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // マスターデータの投入なので書き込みを許可する
        _BaseMst::allowWrites();

        // 既存データをクリア
        DB::connection('mst')->table('mst_gacha_step_bonus_content')->truncate();
        DB::connection('mst')->table('mst_gacha_step_bonus')->truncate();
        DB::connection('mst')->table('mst_gacha_step')->truncate();
        DB::connection('mst')->table('mst_gacha_prize')->truncate();
        DB::connection('mst')->table('mst_gacha_rarity_rate')->truncate();
        DB::connection('mst')->table('mst_gacha_cost')->truncate();
        DB::connection('mst')->table('mst_gacha')->truncate();

        // 1. 通常ガチャ（10連でSSR確定）
        $this->createNormalGacha();

        // 2. ステップアップガチャ（3ステップ、最終ステップでSSR確定選択）
        $this->createStepUpGacha();

        // 3. ピックアップガチャ（特定ユニット排出率アップ）
        $this->createPickupGacha();
    }

    /**
     * 通常ガチャを作成
     */
    private function createNormalGacha(): void
    {
        $gachaId = 'gacha_normal_001';

        // ガチャ基本情報
        MstGacha::create([
            'id' => $gachaId,
            'deploy_key' => 202601010,
            'gacha_type' => 'normal',
            'start_at' => '2026-01-01 00:00:00',
            'end_at' => '2099-12-31 23:59:59',
            'daily_limit' => null,
        ]);

        // コスト設定（単発: 有償石300 / 10連: 有償石3000）
        MstGachaCost::create([
            'id' => 'gacha_cost_normal_single',
            'mst_gacha_id' => $gachaId,
            'draw_count' => 1,
            'cost_type' => 'paid_diamond',
            'cost_amount' => 300,
            'mst_item_id' => null,
        ]);

        MstGachaCost::create([
            'id' => 'gacha_cost_normal_10x',
            'mst_gacha_id' => $gachaId,
            'draw_count' => 10,
            'cost_type' => 'paid_diamond',
            'cost_amount' => 3000,
            'mst_item_id' => null,
        ]);

        // レアリティ確率（10000分率）
        MstGachaRarityRate::create([
            'id' => 'gacha_rate_normal_ssr',
            'mst_gacha_id' => $gachaId,
            'rarity' => 'SSR',
            'rate' => 300, // 3%
        ]);

        MstGachaRarityRate::create([
            'id' => 'gacha_rate_normal_sr',
            'mst_gacha_id' => $gachaId,
            'rarity' => 'SR',
            'rate' => 1500, // 15%
        ]);

        MstGachaRarityRate::create([
            'id' => 'gacha_rate_normal_r',
            'mst_gacha_id' => $gachaId,
            'rarity' => 'R',
            'rate' => 8200, // 82%
        ]);

        // 景品設定
        $prizes = [
            // SSR ユニット
            ['id' => 'gacha_prize_normal_ssr_unit_001', 'rarity' => 'SSR', 'type' => 'unit', 'target_id' => 'unit_ssr_001', 'weight' => 1],
            ['id' => 'gacha_prize_normal_ssr_unit_002', 'rarity' => 'SSR', 'type' => 'unit', 'target_id' => 'unit_ssr_002', 'weight' => 1],
            ['id' => 'gacha_prize_normal_ssr_unit_003', 'rarity' => 'SSR', 'type' => 'unit', 'target_id' => 'unit_ssr_003', 'weight' => 1],

            // SR ユニット
            ['id' => 'gacha_prize_normal_sr_unit_001', 'rarity' => 'SR', 'type' => 'unit', 'target_id' => 'unit_sr_001', 'weight' => 2],
            ['id' => 'gacha_prize_normal_sr_unit_002', 'rarity' => 'SR', 'type' => 'unit', 'target_id' => 'unit_sr_002', 'weight' => 2],
            ['id' => 'gacha_prize_normal_sr_unit_003', 'rarity' => 'SR', 'type' => 'unit', 'target_id' => 'unit_sr_003', 'weight' => 2],

            // R ユニット
            ['id' => 'gacha_prize_normal_r_unit_001', 'rarity' => 'R', 'type' => 'unit', 'target_id' => 'unit_r_001', 'weight' => 5],
            ['id' => 'gacha_prize_normal_r_unit_002', 'rarity' => 'R', 'type' => 'unit', 'target_id' => 'unit_r_002', 'weight' => 5],
            ['id' => 'gacha_prize_normal_r_unit_003', 'rarity' => 'R', 'type' => 'unit', 'target_id' => 'unit_r_003', 'weight' => 5],
        ];

        foreach ($prizes as $prize) {
            MstGachaPrize::create([
                'id' => $prize['id'],
                'mst_gacha_id' => $gachaId,
                'rarity' => $prize['rarity'],
                'prize_type' => $prize['type'],
                'prize_target_id' => $prize['target_id'],
                'prize_amount' => 1,
                'weight' => $prize['weight'],
            ]);
        }
    }

    /**
     * ステップアップガチャを作成
     */
    private function createStepUpGacha(): void
    {
        $gachaId = 'gacha_stepup_001';

        // ガチャ基本情報
        MstGacha::create([
            'id' => $gachaId,
            'deploy_key' => 202601010,
            'gacha_type' => 'step_up',
            'start_at' => '2026-04-01 00:00:00',
            'end_at' => '2026-04-30 23:59:59',
            'daily_limit' => null,
        ]);

        // ステップ1: 10連2000石、SR1体確定
        $step1Id = 'gacha_step_stepup_001_step1';
        MstGachaStep::create([
            'id' => $step1Id,
            'mst_gacha_id' => $gachaId,
            'step_number' => 1,
        ]);

        MstGachaCost::create([
            'id' => 'gacha_cost_stepup_step1',
            'mst_gacha_id' => $gachaId,
            'mst_gacha_step_id' => $step1Id,
            'draw_count' => 10,
            'cost_type' => 'paid_diamond',
            'cost_amount' => 2000,
            'mst_item_id' => null,
        ]);

        MstGachaStepBonus::create([
            'id' => 'gacha_bonus_step1',
            'mst_gacha_step_id' => $step1Id,
            'bonus_type' => 'none',
            'bonus_rarity' => 'SR',
            'bonus_count' => 1,
            'position' => 10,
        ]);

        // ステップ2: 10連2500石、SSR1体確定（ランダム）
        $step2Id = 'gacha_step_stepup_001_step2';
        MstGachaStep::create([
            'id' => $step2Id,
            'mst_gacha_id' => $gachaId,
            'step_number' => 2,
        ]);

        MstGachaCost::create([
            'id' => 'gacha_cost_stepup_step2',
            'mst_gacha_id' => $gachaId,
            'mst_gacha_step_id' => $step2Id,
            'draw_count' => 10,
            'cost_type' => 'paid_diamond',
            'cost_amount' => 2500,
            'mst_item_id' => null,
        ]);

        $bonus2Id = 'gacha_bonus_step2';
        MstGachaStepBonus::create([
            'id' => $bonus2Id,
            'mst_gacha_step_id' => $step2Id,
            'bonus_type' => 'random',
            'bonus_rarity' => 'SSR',
            'bonus_count' => 1,
            'position' => 10,
        ]);

        // ランダム候補（unit_ssr_001 または unit_ssr_002）
        MstGachaStepBonusContent::create([
            'mst_gacha_step_bonus_id' => $bonus2Id,
            'prize_type' => 'unit',
            'prize_target_id' => 'unit_ssr_001',
            'prize_amount' => 1,
        ]);

        MstGachaStepBonusContent::create([
            'mst_gacha_step_bonus_id' => $bonus2Id,
            'prize_type' => 'unit',
            'prize_target_id' => 'unit_ssr_002',
            'prize_amount' => 1,
        ]);

        // ステップ3: 10連3000石、SSR1体確定（選択式）
        $step3Id = 'gacha_step_stepup_001_step3';
        MstGachaStep::create([
            'id' => $step3Id,
            'mst_gacha_id' => $gachaId,
            'step_number' => 3,
        ]);

        MstGachaCost::create([
            'id' => 'gacha_cost_stepup_step3',
            'mst_gacha_id' => $gachaId,
            'mst_gacha_step_id' => $step3Id,
            'draw_count' => 10,
            'cost_type' => 'paid_diamond',
            'cost_amount' => 3000,
            'mst_item_id' => null,
        ]);

        $bonus3Id = 'gacha_bonus_step3';
        MstGachaStepBonus::create([
            'id' => $bonus3Id,
            'mst_gacha_step_id' => $step3Id,
            'bonus_type' => 'choice',
            'bonus_rarity' => 'SSR',
            'bonus_count' => 1,
            'position' => 10,
        ]);

        // 選択候補（unit_ssr_001, unit_ssr_002, unit_ssr_003）
        MstGachaStepBonusContent::create([
            'mst_gacha_step_bonus_id' => $bonus3Id,
            'prize_type' => 'unit',
            'prize_target_id' => 'unit_ssr_001',
            'prize_amount' => 1,
        ]);

        MstGachaStepBonusContent::create([
            'mst_gacha_step_bonus_id' => $bonus3Id,
            'prize_type' => 'unit',
            'prize_target_id' => 'unit_ssr_002',
            'prize_amount' => 1,
        ]);

        MstGachaStepBonusContent::create([
            'mst_gacha_step_bonus_id' => $bonus3Id,
            'prize_type' => 'unit',
            'prize_target_id' => 'unit_ssr_003',
            'prize_amount' => 1,
        ]);

        // レアリティ確率（ステップアップガチャ共通）
        MstGachaRarityRate::create([
            'id' => 'gacha_rate_stepup_ssr',
            'mst_gacha_id' => $gachaId,
            'rarity' => 'SSR',
            'rate' => 500, // 5%
        ]);

        MstGachaRarityRate::create([
            'id' => 'gacha_rate_stepup_sr',
            'mst_gacha_id' => $gachaId,
            'rarity' => 'SR',
            'rate' => 2000, // 20%
        ]);

        MstGachaRarityRate::create([
            'id' => 'gacha_rate_stepup_r',
            'mst_gacha_id' => $gachaId,
            'rarity' => 'R',
            'rate' => 7500, // 75%
        ]);

        // 景品設定（通常ガチャと同じプール）
        $prizes = [
            ['id' => 'gacha_prize_stepup_ssr_unit_001', 'rarity' => 'SSR', 'type' => 'unit', 'target_id' => 'unit_ssr_001', 'weight' => 1],
            ['id' => 'gacha_prize_stepup_ssr_unit_002', 'rarity' => 'SSR', 'type' => 'unit', 'target_id' => 'unit_ssr_002', 'weight' => 1],
            ['id' => 'gacha_prize_stepup_ssr_unit_003', 'rarity' => 'SSR', 'type' => 'unit', 'target_id' => 'unit_ssr_003', 'weight' => 1],
            ['id' => 'gacha_prize_stepup_sr_unit_001', 'rarity' => 'SR', 'type' => 'unit', 'target_id' => 'unit_sr_001', 'weight' => 2],
            ['id' => 'gacha_prize_stepup_sr_unit_002', 'rarity' => 'SR', 'type' => 'unit', 'target_id' => 'unit_sr_002', 'weight' => 2],
            ['id' => 'gacha_prize_stepup_sr_unit_003', 'rarity' => 'SR', 'type' => 'unit', 'target_id' => 'unit_sr_003', 'weight' => 2],
            ['id' => 'gacha_prize_stepup_r_unit_001', 'rarity' => 'R', 'type' => 'unit', 'target_id' => 'unit_r_001', 'weight' => 5],
            ['id' => 'gacha_prize_stepup_r_unit_002', 'rarity' => 'R', 'type' => 'unit', 'target_id' => 'unit_r_002', 'weight' => 5],
            ['id' => 'gacha_prize_stepup_r_unit_003', 'rarity' => 'R', 'type' => 'unit', 'target_id' => 'unit_r_003', 'weight' => 5],
        ];

        foreach ($prizes as $prize) {
            MstGachaPrize::create([
                'id' => $prize['id'],
                'mst_gacha_id' => $gachaId,
                'rarity' => $prize['rarity'],
                'prize_type' => $prize['type'],
                'prize_target_id' => $prize['target_id'],
                'prize_amount' => 1,
                'weight' => $prize['weight'],
            ]);
        }
    }

    /**
     * ピックアップガチャを作成
     */
    private function createPickupGacha(): void
    {
        $gachaId = 'gacha_pickup_001';

        // ガチャ基本情報
        MstGacha::create([
            'id' => $gachaId,
            'deploy_key' => 202601010,
            'gacha_type' => 'pickup',
            'start_at' => '2026-04-01 00:00:00',
            'end_at' => '2026-04-15 23:59:59',
            'daily_limit' => 10, // 1日10回まで
        ]);

        // コスト設定
        MstGachaCost::create([
            'id' => 'gacha_cost_pickup_single',
            'mst_gacha_id' => $gachaId,
            'draw_count' => 1,
            'cost_type' => 'paid_diamond',
            'cost_amount' => 300,
            'mst_item_id' => null,
        ]);

        MstGachaCost::create([
            'id' => 'gacha_cost_pickup_10x',
            'mst_gacha_id' => $gachaId,
            'draw_count' => 10,
            'cost_type' => 'paid_diamond',
            'cost_amount' => 3000,
            'mst_item_id' => null,
        ]);

        // レアリティ確率
        MstGachaRarityRate::create([
            'id' => 'gacha_rate_pickup_ssr',
            'mst_gacha_id' => $gachaId,
            'rarity' => 'SSR',
            'rate' => 600, // 6%（通常の2倍）
        ]);

        MstGachaRarityRate::create([
            'id' => 'gacha_rate_pickup_sr',
            'mst_gacha_id' => $gachaId,
            'rarity' => 'SR',
            'rate' => 1500,
        ]);

        MstGachaRarityRate::create([
            'id' => 'gacha_rate_pickup_r',
            'mst_gacha_id' => $gachaId,
            'rarity' => 'R',
            'rate' => 7900,
        ]);

        // 景品設定（ピックアップキャラの重みを高く）
        $prizes = [
            // SSR ピックアップ（高確率）
            ['id' => 'gacha_prize_pickup_ssr_unit_001', 'rarity' => 'SSR', 'type' => 'unit', 'target_id' => 'unit_ssr_001', 'weight' => 10],
            // SSR その他
            ['id' => 'gacha_prize_pickup_ssr_unit_002', 'rarity' => 'SSR', 'type' => 'unit', 'target_id' => 'unit_ssr_002', 'weight' => 1],
            ['id' => 'gacha_prize_pickup_ssr_unit_003', 'rarity' => 'SSR', 'type' => 'unit', 'target_id' => 'unit_ssr_003', 'weight' => 1],
            // SR
            ['id' => 'gacha_prize_pickup_sr_unit_001', 'rarity' => 'SR', 'type' => 'unit', 'target_id' => 'unit_sr_001', 'weight' => 2],
            ['id' => 'gacha_prize_pickup_sr_unit_002', 'rarity' => 'SR', 'type' => 'unit', 'target_id' => 'unit_sr_002', 'weight' => 2],
            ['id' => 'gacha_prize_pickup_sr_unit_003', 'rarity' => 'SR', 'type' => 'unit', 'target_id' => 'unit_sr_003', 'weight' => 2],
            // R
            ['id' => 'gacha_prize_pickup_r_unit_001', 'rarity' => 'R', 'type' => 'unit', 'target_id' => 'unit_r_001', 'weight' => 5],
            ['id' => 'gacha_prize_pickup_r_unit_002', 'rarity' => 'R', 'type' => 'unit', 'target_id' => 'unit_r_002', 'weight' => 5],
            ['id' => 'gacha_prize_pickup_r_unit_003', 'rarity' => 'R', 'type' => 'unit', 'target_id' => 'unit_r_003', 'weight' => 5],
        ];

        foreach ($prizes as $prize) {
            MstGachaPrize::create([
                'id' => $prize['id'],
                'mst_gacha_id' => $gachaId,
                'rarity' => $prize['rarity'],
                'prize_type' => $prize['type'],
                'prize_target_id' => $prize['target_id'],
                'prize_amount' => 1,
                'weight' => $prize['weight'],
            ]);
        }
    }
}
