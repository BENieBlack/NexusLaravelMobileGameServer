<?php

namespace Database\Seeders;

use App\Models\Mst\MstItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\Mst\_BaseMst;

class MstItemSeeder extends Seeder
{
    /** mst_item と mst_item__l10n で揃える deploy_key */
    private const DEPLOY_KEY = 202601010;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // マスターデータの投入なので書き込みを許可する
        _BaseMst::allowWrites();

        // 既存データをクリア。表示名（mst_item__l10n）も mst_item と一緒に消して作り直す
        // （どちらか片方だけ残ると、行の過不足や主キー重複で冪等でなくなるため）
        DB::connection('mst')->table('mst_item')->truncate();
        DB::connection('mst')->table('mst_item__l10n')->truncate();

        // 各行は 'name'（ja の表示名。必須）と 'description'（省略可。省略は null）を持つ。
        // ID と名前を別の配列に分けず、足し漏れを防ぐ（設計 free-diamond-item db.md 10.2 節）。
        // 名前は l10n の行へ入れるだけで、mst_item の列ではない
        $items = [
            // 回復アイテム
            [
                'id' => 'item_heal_001',
                'name' => '回復ポーション（小）',
                'type' => 'recovery',
                'effect' => 'heal_hp',
                'value' => 100,
            ],
            [
                'id' => 'item_heal_002',
                'name' => '回復ポーション（中）',
                'type' => 'recovery',
                'effect' => 'heal_hp',
                'value' => 500,
            ],
            [
                'id' => 'item_heal_003',
                'name' => '回復ポーション（大）',
                'type' => 'recovery',
                'effect' => 'heal_hp',
                'value' => 1000,
            ],

            // ステータス強化アイテム
            [
                'id' => 'item_boost_001',
                'name' => '攻撃力アップ薬',
                'type' => 'boost',
                'effect' => 'boost_attack',
                'value' => 1.2,
            ],
            [
                'id' => 'item_boost_002',
                'name' => '防御力アップ薬',
                'type' => 'boost',
                'effect' => 'boost_defense',
                'value' => 1.2,
            ],
            [
                'id' => 'item_boost_003',
                'name' => '素早さアップ薬',
                'type' => 'boost',
                'effect' => 'boost_speed',
                'value' => 1.3,
            ],

            // 経験値アイテム
            [
                'id' => 'item_exp_001',
                'name' => '経験値アイテム（小）',
                'type' => 'experience',
                'effect' => 'add_exp',
                'value' => 1000,
            ],
            [
                'id' => 'item_exp_002',
                'name' => '経験値アイテム（中）',
                'type' => 'experience',
                'effect' => 'add_exp',
                'value' => 5000,
            ],
            [
                'id' => 'item_exp_003',
                'name' => '経験値アイテム（大）',
                'type' => 'experience',
                'effect' => 'add_exp',
                'value' => 10000,
            ],

            // ユニット経験値アイテム
            [
                'id' => 'unit_exp_100',
                'name' => 'ユニット経験値（100）',
                'type' => 'unit_enhancement',
                'effect' => 'unit_exp',
                'value' => 100,
            ],
            [
                'id' => 'unit_exp_1000',
                'name' => 'ユニット経験値（1000）',
                'type' => 'unit_enhancement',
                'effect' => 'unit_exp',
                'value' => 1000,
            ],
            [
                'id' => 'unit_exp_10000',
                'name' => 'ユニット経験値（10000）',
                'type' => 'unit_enhancement',
                'effect' => 'unit_exp',
                'value' => 10000,
            ],
            [
                'id' => 'unit_exp_100000',
                'name' => 'ユニット経験値（100000）',
                'type' => 'unit_enhancement',
                'effect' => 'unit_exp',
                'value' => 100000,
            ],

            // 素材アイテム
            [
                'id' => 'item_material_001',
                'name' => '素材 1',
                'type' => 'material',
                'effect' => 'none',
                'value' => 0,
            ],
            [
                'id' => 'item_material_002',
                'name' => '素材 2',
                'type' => 'material',
                'effect' => 'none',
                'value' => 0,
            ],
            [
                'id' => 'item_material_003',
                'name' => '素材 3',
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
            ['id' => 'gold', 'name' => 'ゴールド', 'type' => 'currency', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'coin', 'name' => 'コイン', 'type' => 'currency', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],

            // スタミナ回復薬（trx_item で持つ。Wallet 管理ではない）。
            // ログインボーナス 5 日目の景品で、/item/use（effect = stamina_recover）で value × 個数だけ
            // スタミナが回復する。type = stamina は設計 free-diamond-item db.md 10.3.1 節の決定
            ['id' => 'stamina_50', 'name' => 'スタミナ回復薬（50）', 'type' => 'stamina', 'effect' => 'stamina_recover', 'value' => 50, 'is_wallet' => false],

            // 無償ダイヤ（trx_item で持つ。Wallet 管理ではないので is_wallet = false）。
            // サインアップ時の初期付与（InitialGrantConst::FREE_DIAMOND_MST_ITEM_ID）が指す
            [
                'id' => 'free_diamond',
                'name' => '無償ダイヤ',
                'type' => 'currency',
                'effect' => 'none',
                'value' => 0,
                'is_wallet' => false,
                // 図鑑（アルバム）に載せない。item 型の配布は図鑑の unlock 対象で、
                // 列の既定は true のため、指定しないと無償ダイヤを受け取るたびに図鑑へ載り、分母にも入る
                'is_album_target' => false,
            ],

            // 自然資源
            ['id' => 'food', 'name' => '食料', 'type' => 'natural_resource', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'wood', 'name' => '木材', 'type' => 'natural_resource', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'stone', 'name' => '石材', 'type' => 'natural_resource', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'iron', 'name' => '鉄', 'type' => 'natural_resource', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],

            // ポイント
            ['id' => 'alliance_points', 'name' => 'アライアンスポイント', 'type' => 'points', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'pvp_points', 'name' => 'PvPポイント', 'type' => 'points', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'event_points', 'name' => 'イベントポイント', 'type' => 'points', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'achievement_points', 'name' => '達成ポイント', 'type' => 'points', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
            ['id' => 'vip_points', 'name' => 'VIPポイント', 'type' => 'points', 'effect' => 'none', 'value' => 0, 'is_wallet' => true],
        ];

        foreach ($items as $itemData) {
            // アイテム本体を作成
            MstItem::create([
                'id' => $itemData['id'],
                'deploy_key' => self::DEPLOY_KEY,
                'type' => $itemData['type'],
                'effect' => $itemData['effect'],
                'value' => $itemData['value'],
                // 渡さないと列の既定値（false）になり、Wallet 管理のはずの coin・gold が
                // trx_item に振り分けられてしまう。$items に無い行は Wallet 管理ではない
                'is_wallet' => $itemData['is_wallet'] ?? false,
                // 図鑑に載せるか。行で指定した行（free_diamond）以外は列の既定（true）のまま
                ...(isset($itemData['is_album_target']) ? ['is_album_target' => $itemData['is_album_target']] : []),
            ]);

            // 表示名（language = ja）を 1 行入れる。クライアントは ja 固定で、配信された SQLite の
            // mst_item__l10n から名前を引く。他言語は運営がマスター投入で足す。
            // l10n は L10n モデルを持たない方針（設計 U-S10）なのでクエリビルダーで入れる。
            // created_at / updated_at はテーブルの既定値（CURRENT_TIMESTAMP）に任せる
            DB::connection('mst')->table('mst_item__l10n')->insert([
                'deploy_key' => self::DEPLOY_KEY,
                'mst_item_id' => $itemData['id'],
                'language' => 'ja',
                'name' => $itemData['name'],
                'description' => $itemData['description'] ?? null,
            ]);
        }
    }
}
