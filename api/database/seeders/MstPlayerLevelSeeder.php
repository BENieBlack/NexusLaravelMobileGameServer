<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * MstPlayerLevelSeeder
 *
 * プレイヤーレベル1〜100のマスターデータを生成
 *
 * 経験値計算式: floor(level^1.5 * 50)
 * 最大スタミナ計算式: 50 + (level - 1) * 2
 *
 * レベル1: 50 EXP, 50 スタミナ
 * レベル50: 17,677 EXP, 148 スタミナ
 * レベル100: 約202万 EXP累積, 248 スタミナ
 */
class MstPlayerLevelSeeder extends Seeder
{
    /**
     * 最大レベル
     */
    private const MAX_LEVEL = 100;

    /**
     * 基本最大スタミナ（レベル1）
     */
    private const BASE_MAX_STAMINA = 50;

    /**
     * レベル毎の最大スタミナ増加量
     */
    private const STAMINA_PER_LEVEL = 2;

    /**
     * デプロイキー
     */
    private const DEPLOY_KEY = 202601010;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 既存データをクリア
        DB::connection('mst')->table('mst_player_level')->truncate();

        $levels = [];

        for ($level = 1; $level <= self::MAX_LEVEL; $level++) {
            $levels[] = [
                'level' => $level,
                'deploy_key' => self::DEPLOY_KEY,
                'required_exp' => $this->calculateRequiredExp($level),
                'max_stamina' => $this->calculateMaxStamina($level),
            ];
        }

        // バッチインサート
        DB::connection('mst')->table('mst_player_level')->insert($levels);

        $this->command->info('プレイヤーレベル1〜'.self::MAX_LEVEL.'のマスターデータを作成しました。');
    }

    /**
     * 指定レベルに到達するために必要な累積経験値を計算
     *
     * 計算式: floor(level^1.5 * 50)
     *
     * @param  int  $level  レベル
     * @return int 累積経験値
     */
    private function calculateRequiredExp(int $level): int
    {
        if ($level <= 1) {
            return 0; // レベル1は初期状態なので0
        }

        return (int) floor(pow($level, 1.5) * 50);
    }

    /**
     * 指定レベルでの最大スタミナを計算
     *
     * 計算式: 50 + (level - 1) * 2
     *
     * @param  int  $level  レベル
     * @return int 最大スタミナ
     */
    private function calculateMaxStamina(int $level): int
    {
        return self::BASE_MAX_STAMINA + ($level - 1) * self::STAMINA_PER_LEVEL;
    }
}
