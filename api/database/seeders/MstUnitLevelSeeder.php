<?php

namespace Database\Seeders;

use App\Domain\Unit\Constants\UnitConst;
use App\Models\Mst\MstUnitLevel;
use Illuminate\Database\Seeder;

/**
 * MstUnitLevelSeeder
 * 
 * ユニットのレベルアップに必要な経験値データを生成
 * レアリティごとに異なる経験値曲線を設定
 */
class MstUnitLevelSeeder extends Seeder
{
    /**
     * レアリティ別の経験値係数
     * 高レアリティほど成長に多くの経験値が必要
     */
    private const RARITY_MULTIPLIERS = [
        UnitConst::RARITY_C => 1.0,     // 基準
        UnitConst::RARITY_UC => 1.2,    // 1.2倍
        UnitConst::RARITY_R => 1.5,     // 1.5倍
        UnitConst::RARITY_SR => 2.0,    // 2倍
        UnitConst::RARITY_SSR => 3.0,   // 3倍
        UnitConst::RARITY_UR => 5.0,    // 5倍
    ];

    /**
     * 最大レベル
     */
    private const MAX_LEVEL = 100;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $data = [];
        $deployKey = 202601010;

        foreach (UnitConst::getAllRarities() as $rarity) {
            $multiplier = self::RARITY_MULTIPLIERS[$rarity];

            for ($level = 1; $level <= self::MAX_LEVEL; $level++) {
                // 累積経験値の計算
                // 経験値曲線: level^2 * 100 * レアリティ倍率
                $requiredExp = (int)floor(pow($level, 2) * 100 * $multiplier);

                $data[] = [
                    'deploy_key' => $deployKey,
                    'rarity' => $rarity,
                    'level' => $level,
                    'required_exp' => $requiredExp,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // バルクインサート
        foreach (array_chunk($data, 500) as $chunk) {
            MstUnitLevel::insert($chunk);
        }

        $this->command->info('MstUnitLevel seeded successfully!');
        $this->command->info('Total records: ' . count($data));
        $this->command->info('Rarities: ' . implode(', ', UnitConst::getAllRarities()));
        $this->command->info('Max Level: ' . self::MAX_LEVEL);
    }
}
