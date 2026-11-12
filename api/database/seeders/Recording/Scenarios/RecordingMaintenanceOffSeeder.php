<?php

namespace Database\Seeders\Recording\Scenarios;

use Database\Seeders\Recording\RecordingScenarioSeeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;

/**
 * RecordingMaintenanceOffSeeder
 *
 * 目的: RecordingMaintenanceOnSeeder で有効にしたメンテナンスを解除する。
 * 既定の一覧には入れない。
 *
 * 使うケース（client_design.md 7節）: 8（解除して再試行 → ホームまで進む）。
 *
 * 冪等性: マーカー行を is_active=false にするだけ。行が無くても何もしない。
 * 副作用: title が `[REC]` で始まる行だけを触る。運営が入れた本物のメンテナンスは解除しない。
 */
class RecordingMaintenanceOffSeeder extends RecordingScenarioSeeder
{
    protected function apply(): void
    {
        DB::connection('sys')->table('sys_maintenance')
            ->where('title', 'like', self::MAINTENANCE_TITLE_PREFIX.'%')
            ->update(['is_active' => false, 'updated_at' => ClockUtility::nowToString()]);

        $this->clearApiCaches();

        $this->report('動画用のメンテナンスを解除しました。');
    }
}
