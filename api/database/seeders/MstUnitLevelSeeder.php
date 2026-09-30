<?php

namespace Database\Seeders;

use App\Domain\Common\Constants\RarityType;
use App\Models\Mst\MstUnitLevel;
use Illuminate\Database\Seeder;
use Nexus\Core\Models\Mst\_BaseMst;

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
        RarityType::C => 1.0,     // 基準
        RarityType::UC => 1.2,    // 1.2倍
        RarityType::R => 1.5,     // 1.5倍
        RarityType::SR => 2.0,    // 2倍
        RarityType::SSR => 3.0,   // 3倍
        RarityType::UR => 5.0,    // 5倍
    ];

    /**
     * 最大レベル
     */
    private const MAX_LEVEL = 100;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // マスターデータの投入なので書き込みを許可する
        _BaseMst::allowWrites();

        $data = [];
        $deployKey = 202601010;

        foreach (RarityType::all() as $rarity) {
            $multiplier = self::RARITY_MULTIPLIERS[$rarity];

            for ($level = 1; $level <= self::MAX_LEVEL; $level++) {
                // 累積経験値の計算
                // 経験値曲線: level^2 * 100 * レアリティ倍率
                $requiredExp = (int) floor(pow($level, 2) * 100 * $multiplier);

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
        $this->command->info('Total records: '.count($data));
        $this->command->info('Rarities: '.implode(', ', RarityType::all()));
        $this->command->info('Max Level: '.self::MAX_LEVEL);
    }
}
