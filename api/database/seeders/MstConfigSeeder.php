<?php

namespace Database\Seeders;

use App\Domain\MstConfig\Constants\MstConfigKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\Mst\_BaseMst;

/**
 * 設定マスター（mst_config）のシーダー
 *
 * 行は MstConfigKey の全 case から作る（初期値 = 既定値。数値はここに書かない）。
 * created_at / updated_at は DB の既定値に任せる（他の mst シーダーと同じ）。
 * 他の mst シーダーと同じく truncate + insert なので、何度流しても同じ結果になる。
 * mst_config だけを触り、他のマスターには影響しない。
 */
class MstConfigSeeder extends Seeder
{
    private const DEPLOY_KEY = 202601010;

    public function run(): void
    {
        // マスターデータの投入なので書き込みを許可する
        _BaseMst::allowWrites();

        // 既存データをクリア
        DB::connection('mst')->table('mst_config')->truncate();

        $rows = [];

        foreach (MstConfigKey::cases() as $key) {
            $rows[] = [
                'deploy_key' => self::DEPLOY_KEY,
                'id' => $key->value,
                'value' => $key->initialValue(),
                'value_type' => $key->valueType()->value,
                'description' => $key->description(),
            ];
        }

        DB::connection('mst')->table('mst_config')->insert($rows);
    }
}
