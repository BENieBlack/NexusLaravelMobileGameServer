<?php

namespace Database\Seeders;

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
                'element' => UnitConst::ELEMENT_FIRE,
                'rarity' => UnitConst::RARITY_SSR,
            ],
            [
                'id' => 'unit_fire_002',
                'type' => UnitConst::TYPE_SUPPORT,
                'element' => UnitConst::ELEMENT_FIRE,
                'rarity' => UnitConst::RARITY_SR,
            ],

            // 水属性
            [
                'id' => 'unit_water_001',
                'type' => UnitConst::TYPE_DEFENSE,
                'element' => UnitConst::ELEMENT_WATER,
                'rarity' => UnitConst::RARITY_SSR,
            ],
            [
                'id' => 'unit_water_002',
                'type' => UnitConst::TYPE_ATTACK,
                'element' => UnitConst::ELEMENT_WATER,
                'rarity' => UnitConst::RARITY_UR,
            ],

            // 風属性
            [
                'id' => 'unit_wind_001',
                'type' => UnitConst::TYPE_ATTACK,
                'element' => UnitConst::ELEMENT_WIND,
                'rarity' => UnitConst::RARITY_SR,
            ],
            [
                'id' => 'unit_wind_002',
                'type' => UnitConst::TYPE_SUPPORT,
                'element' => UnitConst::ELEMENT_WIND,
                'rarity' => UnitConst::RARITY_R,
            ],

            // 地属性
            [
                'id' => 'unit_earth_001',
                'type' => UnitConst::TYPE_DEFENSE,
                'element' => UnitConst::ELEMENT_EARTH,
                'rarity' => UnitConst::RARITY_SSR,
            ],
            [
                'id' => 'unit_earth_002',
                'type' => UnitConst::TYPE_ATTACK,
                'element' => UnitConst::ELEMENT_EARTH,
                'rarity' => UnitConst::RARITY_SR,
            ],

            // 光属性
            [
                'id' => 'unit_light_001',
                'type' => UnitConst::TYPE_SUPPORT,
                'element' => UnitConst::ELEMENT_LIGHT,
                'rarity' => UnitConst::RARITY_UR,
            ],
            [
                'id' => 'unit_light_002',
                'type' => UnitConst::TYPE_ATTACK,
                'element' => UnitConst::ELEMENT_LIGHT,
                'rarity' => UnitConst::RARITY_SSR,
            ],

            // 闇属性
            [
                'id' => 'unit_dark_001',
                'type' => UnitConst::TYPE_ATTACK,
                'element' => UnitConst::ELEMENT_DARK,
                'rarity' => UnitConst::RARITY_UR,
            ],
            [
                'id' => 'unit_dark_002',
                'type' => UnitConst::TYPE_DEFENSE,
                'element' => UnitConst::ELEMENT_DARK,
                'rarity' => UnitConst::RARITY_SR,
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
