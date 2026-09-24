<?php

namespace Database\Seeders;

use App\Domain\Common\Constants\ElementType;
use App\Domain\Common\Constants\RarityType;
use App\Domain\Unit\Constants\UnitConst;
use App\Models\Mst\MstUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\Mst\_BaseMst;

class MstUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // マスターデータの投入なので書き込みを許可する
        _BaseMst::allowWrites();

        // 既存データをクリア
        DB::connection('mst')->table('mst_unit')->truncate();

        $units = [
            // 火属性
            [
                'id' => 'unit_fire_001',
                'type' => UnitConst::TYPE_ATTACK,
                'element' => ElementType::FIRE,
                'rarity' => RarityType::SSR,
            ],
            [
                'id' => 'unit_fire_002',
                'type' => UnitConst::TYPE_SUPPORT,
                'element' => ElementType::FIRE,
                'rarity' => RarityType::SR,
            ],

            // 水属性
            [
                'id' => 'unit_water_001',
                'type' => UnitConst::TYPE_DEFENSE,
                'element' => ElementType::WATER,
                'rarity' => RarityType::SSR,
            ],
            [
                'id' => 'unit_water_002',
                'type' => UnitConst::TYPE_ATTACK,
                'element' => ElementType::WATER,
                'rarity' => RarityType::UR,
            ],

            // 風属性
            [
                'id' => 'unit_wind_001',
                'type' => UnitConst::TYPE_ATTACK,
                'element' => ElementType::WIND,
                'rarity' => RarityType::SR,
            ],
            [
                'id' => 'unit_wind_002',
                'type' => UnitConst::TYPE_SUPPORT,
                'element' => ElementType::WIND,
                'rarity' => RarityType::R,
            ],

            // 地属性
            [
                'id' => 'unit_earth_001',
                'type' => UnitConst::TYPE_DEFENSE,
                'element' => ElementType::EARTH,
                'rarity' => RarityType::SSR,
            ],
            [
                'id' => 'unit_earth_002',
                'type' => UnitConst::TYPE_ATTACK,
                'element' => ElementType::EARTH,
                'rarity' => RarityType::SR,
            ],

            // 光属性
            [
                'id' => 'unit_light_001',
                'type' => UnitConst::TYPE_SUPPORT,
                'element' => ElementType::LIGHT,
                'rarity' => RarityType::UR,
            ],
            [
                'id' => 'unit_light_002',
                'type' => UnitConst::TYPE_ATTACK,
                'element' => ElementType::LIGHT,
                'rarity' => RarityType::SSR,
            ],

            // 闇属性
            [
                'id' => 'unit_dark_001',
                'type' => UnitConst::TYPE_ATTACK,
                'element' => ElementType::DARK,
                'rarity' => RarityType::UR,
            ],
            [
                'id' => 'unit_dark_002',
                'type' => UnitConst::TYPE_DEFENSE,
                'element' => ElementType::DARK,
                'rarity' => RarityType::SR,
            ],
        ];

        foreach ($units as $unitData) {
            MstUnit::create([
                'id' => $unitData['id'],
                'deploy_key' => 202601010,
                'type' => $unitData['type'],
                'element' => $unitData['element'],
                'rarity' => $unitData['rarity'],
            ]);
        }
    }
}
