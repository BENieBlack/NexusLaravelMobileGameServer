<?php

namespace Database\Seeders\Recording\Scenarios;

use Database\Seeders\Recording\RecordingScenarioSeeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;

/**
 * RecordingMaintenanceOnSeeder
 *
 * 目的: メンテナンス中（503）の画面を撮るため、有効なメンテナンスを1件作る。
 * 既定の一覧には入れない（API が止まり、他の撮影ができなくなる）。
 *
 * 使うケース（client_design.md 7節）: 8（メンテナンス 503）。
 * 解除は RecordingMaintenanceOffSeeder（または RecordingResetSeeder）。
 *
 * 冪等性: title が `[REC]` で始まる行をタイトルで updateOrInsert。何度流しても1行。
 * 開始・終了は流すたびに「1時間前〜1日後」へ取り直す（古い時刻のまま期限切れにならないため）。
 * 副作用: 他のメンテナンス行は触らない。ただし別の有効な行があれば、それもメンテナンス扱いになる。
 * キャッシュ: 503 のミドルウェア（60秒）と version API（3600秒）のキャッシュを末尾で消すので、
 * 流した直後に反映される。DB を他の手段で直接変えたときは TTL を待つか、キャッシュを消す。
 */
class RecordingMaintenanceOnSeeder extends RecordingScenarioSeeder
{
    protected function apply(): void
    {
        $now = ClockUtility::now();

        DB::connection('sys')->table('sys_maintenance')->updateOrInsert(
            ['title' => self::MAINTENANCE_TITLE_PREFIX.' メンテナンスのお知らせ'],
            [
                'message' => '動画撮影用のメンテナンスです。しばらくお待ちください。',
                'start_at' => $now->subHour()->format('Y-m-d H:i:s'),
                'end_at' => $now->addDay()->format('Y-m-d H:i:s'),
                'is_active' => true,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ],
        );

        $this->clearApiCaches();

        $this->report('メンテナンスを有効にしました。撮影後は RecordingMaintenanceOffSeeder で解除してください。');
    }
}
