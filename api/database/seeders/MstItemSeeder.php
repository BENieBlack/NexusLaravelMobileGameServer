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
                'type' => 'recovery',
                'effect' => 'heal_hp',
                'value' => 100,
            ],
            [
                'id' => 'item_heal_002',
                'type' => 'recovery',
                'effect' => 'heal_hp',
                'value' => 500,
            ],
            [
                'id' => 'item_heal_003',
                'type' => 'recovery',
                'effect' => 'heal_hp',
                'value' => 1000,
            ],

            // ステータス強化アイテム
            [
                'id' => 'item_boost_001',
                'type' => 'boost',
                'effect' => 'boost_attack',
                'value' => 1.2,
            ],
            [
                'id' => 'item_boost_002',
                'type' => 'boost',
                'effect' => 'boost_defense',
                'value' => 1.2,
            ],
            [
                'id' => 'item_boost_003',
                'type' => 'boost',
                'effect' => 'boost_speed',
                'value' => 1.3,
            ],

            // 経験値アイテム
            [
                'id' => 'item_exp_001',
                'type' => 'experience',
                'effect' => 'add_exp',
                'value' => 1000,
            ],
            [
                'id' => 'item_exp_002',
                'type' => 'experience',
                'effect' => 'add_exp',
                'value' => 5000,
            ],
            [
                'id' => 'item_exp_003',
                'type' => 'experience',
                'effect' => 'add_exp',
                'value' => 10000,
            ],

            // ユニット経験値アイテム
            [
                'id' => 'unit_exp_100',
                'type' => 'unit_enhancement',
                'effect' => 'unit_exp',
                'value' => 100,
            ],
            [
                'id' => 'unit_exp_1000',
                'type' => 'unit_enhancement',
                'effect' => 'unit_exp',
                'value' => 1000,
            ],
            [
                'id' => 'unit_exp_10000',
                'type' => 'unit_enhancement',
                'effect' => 'unit_exp',
                'value' => 10000,
            ],
            [
                'id' => 'unit_exp_100000',
                'type' => 'unit_enhancement',
                'effect' => 'unit_exp',
                'value' => 100000,
            ],

            // 素材アイテム
            [
                'id' => 'item_material_001',
                'type' => 'material',
                'effect' => 'none',
                'value' => 0,
            ],
            [
                'id' => 'item_material_002',
                'type' => 'material',
                'effect' => 'none',
                'value' => 0,
            ],
            [
                'id' => 'item_material_003',
                'type' => 'material',
                'effect' => 'none',
                'value' => 0,
            ],

            // ========================================
            // Wallet管理のアイテム（残高として持つもの）
            //
            // trx_item ではなく trx_wallet 系で扱う。
            // ResourceType の値と id を合わせており、
            // 配送側の Resource::gold() などがそのまま指す
            // ========================================

            // 通貨
            ['id' => 'gold', 'type' => 'currency', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'coin', 'type' => 'currency', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],

            // 自然資源
            ['id' => 'food', 'type' => 'natural_resource', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'wood', 'type' => 'natural_resource', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'stone', 'type' => 'natural_resource', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'iron', 'type' => 'natural_resource', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],

            // ポイント
            ['id' => 'alliance_points', 'type' => 'points', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'pvp_points', 'type' => 'points', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'event_points', 'type' => 'points', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'achievement_points', 'type' => 'points', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'vip_points', 'type' => 'points', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
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
