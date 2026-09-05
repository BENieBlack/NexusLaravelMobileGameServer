<?php

namespace Database\Seeders;

use App\Domain\Common\Constants\ElementType;
use App\Domain\Common\Constants\RarityType;
use App\Domain\Equipment\Constants\EquipmentConst;
use App\Models\Mst\MstEquipment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\Mst\_BaseMst;

class MstEquipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // マスターデータの投入なので書き込みを許可する
        _BaseMst::allowWrites();

        // 既存データをクリア
        DB::connection('mst')->table('mst_equipment')->truncate();

        $equipments = [
            // 火属性武器
            [
                'id' => 'equip_fire_sword_001',
                'type' => EquipmentConst::TYPE_ATTACK,
                'element' => ElementType::FIRE,
                'rarity' => RarityType::SSR,
                'attack' => 350,
                'defense' => 50,
                'hp' => 200,
                'sort_desc' => 1000,
                'is_active' => true,
            ],
            [
                'id' => 'equip_fire_staff_001',
                'type' => EquipmentConst::TYPE_SUPPORT,
                'element' => ElementType::FIRE,
                'rarity' => RarityType::UR,
                'attack' => 280,
                'defense' => 80,
                'hp' => 300,
                'sort_desc' => 2000,
                'is_active' => true,
            ],

            // 水属性武器
            [
                'id' => 'equip_water_lance_001',
                'type' => EquipmentConst::TYPE_ATTACK,
                'element' => ElementType::WATER,
                'rarity' => RarityType::SSR,
                'attack' => 320,
                'defense' => 70,
                'hp' => 250,
                'sort_desc' => 900,
                'is_active' => true,
            ],
            [
                'id' => 'equip_water_shield_001',
                'type' => EquipmentConst::TYPE_DEFENSE,
                'element' => ElementType::WATER,
                'rarity' => RarityType::UR,
                'attack' => 50,
                'defense' => 400,
                'hp' => 500,
                'sort_desc' => 1900,
                'is_active' => true,
            ],

            // 風属性武器
            [
                'id' => 'equip_wind_bow_001',
                'type' => EquipmentConst::TYPE_ATTACK,
                'element' => ElementType::WIND,
                'rarity' => RarityType::SR,
                'attack' => 290,
                'defense' => 40,
                'hp' => 150,
                'sort_desc' => 700,
                'is_active' => true,
            ],
            [
                'id' => 'equip_wind_dagger_001',
                'type' => EquipmentConst::TYPE_ATTACK,
                'element' => ElementType::WIND,
                'rarity' => RarityType::SSR,
                'attack' => 310,
                'defense' => 60,
                'hp' => 180,
                'sort_desc' => 800,
                'is_active' => true,
            ],

            // 地属性武器
            [
                'id' => 'equip_earth_hammer_001',
                'type' => EquipmentConst::TYPE_ATTACK,
                'element' => ElementType::EARTH,
                'rarity' => RarityType::SR,
                'attack' => 330,
                'defense' => 100,
                'hp' => 350,
                'sort_desc' => 750,
                'is_active' => true,
            ],
            [
                'id' => 'equip_earth_armor_001',
                'type' => EquipmentConst::TYPE_DEFENSE,
                'element' => ElementType::EARTH,
                'rarity' => RarityType::SSR,
                'attack' => 70,
                'defense' => 380,
                'hp' => 450,
                'sort_desc' => 1800,
                'is_active' => true,
            ],

            // 光属性武器
            [
                'id' => 'equip_light_sword_001',
                'type' => EquipmentConst::TYPE_ATTACK,
                'element' => ElementType::LIGHT,
                'rarity' => RarityType::UR,
                'attack' => 380,
                'defense' => 90,
                'hp' => 280,
                'sort_desc' => 2100,
                'is_active' => true,
            ],
            [
                'id' => 'equip_light_staff_001',
                'type' => EquipmentConst::TYPE_SUPPORT,
                'element' => ElementType::LIGHT,
                'rarity' => RarityType::SSR,
                'attack' => 250,
                'defense' => 100,
                'hp' => 320,
                'sort_desc' => 1700,
                'is_active' => true,
            ],

            // 闇属性武器
            [
                'id' => 'equip_dark_scythe_001',
                'type' => EquipmentConst::TYPE_ATTACK,
                'element' => ElementType::DARK,
                'rarity' => RarityType::UR,
                'attack' => 400,
                'defense' => 60,
                'hp' => 220,
                'sort_desc' => 2200,
                'is_active' => true,
            ],
            [
                'id' => 'equip_dark_cloak_001',
                'type' => EquipmentConst::TYPE_DEFENSE,
                'element' => ElementType::DARK,
                'rarity' => RarityType::SSR,
                'attack' => 80,
                'defense' => 320,
                'hp' => 380,
                'sort_desc' => 1600,
                'is_active' => true,
            ],
        ];

        foreach ($equipments as $equipData) {
            // 装備本体を作成
            MstEquipment::create([
                'id' => $equipData['id'],
                'deploy_key' => 202601010,
                'type' => $equipData['type'],
                'element' => $equipData['element'],
                'rarity' => $equipData['rarity'],
                'attack' => $equipData['attack'],
                'defense' => $equipData['defense'],
                'hp' => $equipData['hp'],
                'sort_desc' => $equipData['sort_desc'],
                'is_active' => $equipData['is_active'],
            ]);
        }
    }
}
