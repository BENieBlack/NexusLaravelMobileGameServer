<?php

namespace Database\Seeders;

use App\Models\Mst\MstItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\Mst\_BaseMst;

class MstItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // マスターデータの投入なので書き込みを許可する
        _BaseMst::allowWrites();

        // 既存データをクリア
        DB::connection('mst')->table('mst_item')->truncate();

        $items = [
            // 回復アイテム
            [
                'id' => 'item_heal_001',
                'type' => 'Recovery',
                'effect' => 'HealHP',
                'value' => 100,
            ],
            [
                'id' => 'item_heal_002',
                'type' => 'Recovery',
                'effect' => 'HealHP',
                'value' => 500,
            ],
            [
                'id' => 'item_heal_003',
                'type' => 'Recovery',
                'effect' => 'HealHP',
                'value' => 1000,
            ],

            // ステータス強化アイテム
            [
                'id' => 'item_boost_001',
                'type' => 'Boost',
                'effect' => 'BoostAttack',
                'value' => 1.2,
            ],
            [
                'id' => 'item_boost_002',
                'type' => 'Boost',
                'effect' => 'BoostDefense',
                'value' => 1.2,
            ],
            [
                'id' => 'item_boost_003',
                'type' => 'Boost',
                'effect' => 'BoostSpeed',
                'value' => 1.3,
            ],

            // 経験値アイテム
            [
                'id' => 'item_exp_001',
                'type' => 'Experience',
                'effect' => 'AddExp',
                'value' => 1000,
            ],
            [
                'id' => 'item_exp_002',
                'type' => 'Experience',
                'effect' => 'AddExp',
                'value' => 5000,
            ],
            [
                'id' => 'item_exp_003',
                'type' => 'Experience',
                'effect' => 'AddExp',
                'value' => 10000,
            ],

            // ユニット経験値アイテム
            [
                'id' => 'unit_exp_100',
                'type' => 'UnitEnhancement',
                'effect' => 'UnitExp',
                'value' => 100,
            ],
            [
                'id' => 'unit_exp_1000',
                'type' => 'UnitEnhancement',
                'effect' => 'UnitExp',
                'value' => 1000,
            ],
            [
                'id' => 'unit_exp_10000',
                'type' => 'UnitEnhancement',
                'effect' => 'UnitExp',
                'value' => 10000,
            ],
            [
                'id' => 'unit_exp_100000',
                'type' => 'UnitEnhancement',
                'effect' => 'UnitExp',
                'value' => 100000,
            ],

            // 素材アイテム
            [
                'id' => 'item_material_001',
                'type' => 'Material',
                'effect' => 'None',
                'value' => 0,
            ],
            [
                'id' => 'item_material_002',
                'type' => 'Material',
                'effect' => 'None',
                'value' => 0,
            ],
            [
                'id' => 'item_material_003',
                'type' => 'Material',
                'effect' => 'None',
                'value' => 0,
            ],
        ];

        foreach ($items as $itemData) {
            // アイテム本体を作成
            MstItem::create([
                'id' => $itemData['id'],
                'deploy_key' => 202601010,
                'type' => $itemData['type'],
                'effect' => $itemData['effect'],
                'value' => $itemData['value'],
            ]);
        }
    }
}
