<?php

namespace Database\Seeders\Recording\Scenarios;

use Database\Seeders\Recording\RecordingScenarioSeeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\Mst\_BaseMst;
use Nexus\Core\Utilities\ClockUtility;

/**
 * RecordingLoginBonusDisabledSeeder
 *
 * 目的: 「ログインボーナスなし」の画面を撮るため、daily_login を無効（is_active=false）にする。
 * 既定の一覧には入れない（流すとログインボーナス画面が出なくなる）。
 *
 * 使うケース（client_design.md 7節）: ログインボーナスが無い場合のホームへの遷移。
 * 戻すときは RecordingLoginBonusSeeder か RecordingResetSeeder。
 *
 * 冪等性: 何度流しても is_active=false のまま。
 * 副作用: daily_login の有効フラグだけを変える。行・報酬は消さない。
 */
class RecordingLoginBonusDisabledSeeder extends RecordingScenarioSeeder
{
    protected function apply(): void
    {
        _BaseMst::allowWrites(function (): void {
            DB::connection('mst')->table('mst_login_bonus')
                ->where('id', self::DAILY_LOGIN_BONUS_ID)
                ->update(['is_active' => false, 'updated_at' => ClockUtility::nowToString()]);
        });

        $this->report('通常ログインボーナス（daily_login）を無効にしました。');
    }
}
