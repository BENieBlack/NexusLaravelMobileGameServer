<?php

namespace Database\Seeders\Recording\Scenarios;

use Database\Seeders\Recording\RecordingScenarioSeeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\Mst\_BaseMst;
use Nexus\Core\Utilities\ClockUtility;

/**
 * RecordingLoginBonusSeeder
 *
 * 目的: 通常ログインボーナス daily_login（7日サイクル）を、有効（is_active=true、期間なし）で
 * 存在する状態にする。内容は MstLoginBonusSeeder と同じ。
 *
 * 使うケース（client_design.md 7節）: 1（初回のログインボーナス）、2（受け取り済み）、
 * 3（規約の後のログインボーナス）、5（2日目）、13。
 *
 * 冪等性: mst_login_bonus と mst_login_bonus_content を主キーで updateOrInsert。
 * 無ければ作り、あれば有効に戻す。
 * 副作用: 他のログインボーナス・他の行は消さない（MstLoginBonusSeeder は truncate するので、
 * 動画用ではこちらを使う）。マスターの変更なので、デプロイ済みの配信ファイルには反映されない。
 */
class RecordingLoginBonusSeeder extends RecordingScenarioSeeder
{
    private const LOOP_DAYS = 7;

    protected function apply(): void
    {
        // mst への書き込みは許可された範囲でだけ開く
        _BaseMst::allowWrites(function (): void {
            $this->upsertBonus();
            $this->upsertContents();
        });

        $this->report('通常ログインボーナス（daily_login）を有効にしました。');
    }

    private function upsertBonus(): void
    {
        $now = ClockUtility::nowToString();

        DB::connection('mst')->table('mst_login_bonus')->updateOrInsert(
            ['id' => self::DAILY_LOGIN_BONUS_ID],
            [
                'type' => 'daily',
                'loop_days' => self::LOOP_DAYS,
                'required_absent_days' => null,
                'valid_days' => null,
                'priority' => 0,
                'is_active' => true,
                // 期間なし。期間が残っていると撮影日によっては対象外になる
                'start_at' => null,
                'end_at' => null,
                'updated_at' => $now,
            ],
        );
    }

    private function upsertContents(): void
    {
        $now = ClockUtility::nowToString();

        // MstLoginBonusSeeder と同じ報酬（day => [種別, ID, 数量]）
        $dayRewards = [
            1 => ['item', 'free_diamond', 100],
            2 => ['wallet', 'gold', 5000],
            3 => ['item', 'free_diamond', 100],
            4 => ['wallet', 'gold', 10000],
            5 => ['item', 'stamina_50', 1],
            6 => ['item', 'free_diamond', 20000],
            7 => ['item', 'free_diamond', 500],
        ];

        foreach ($dayRewards as $day => [$type, $mstId, $amount]) {
            DB::connection('mst')->table('mst_login_bonus_content')->updateOrInsert(
                [
                    'mst_login_bonus_id' => self::DAILY_LOGIN_BONUS_ID,
                    'day' => $day,
                    'content_type' => $type,
                    'content_mst_id' => $mstId,
                ],
                [
                    'content_option' => null,
                    'content_quantity' => $amount,
                    // amount は倍率。配布量は content_quantity に入れるので 1（MstLoginBonusSeeder と同じ）
                    'amount' => 1,
                    'is_paid' => false,
                    'sort_order' => 0,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
