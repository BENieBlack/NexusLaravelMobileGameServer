<?php

namespace Database\Seeders\Recording\Scenarios;

use Database\Seeders\Recording\RecordingScenarioSeeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;

/**
 * RecordingAgreementMultiSeeder
 *
 * 目的: 必須規約が複数件あるときの規約画面（client_design.md 7 節のケース 3 の派生。7-2 の表に専用の番号は無い）の確認用に、
 * 公開済みの必須規約を 2 件足す。既存の rec-required（RecordingAgreementSeeder）と合わせて 3 件になる。
 * - rec-required-2: 公開済みの必須規約
 * - rec-required-3: 公開済みの必須規約
 *
 * 使い方: `make seed-recording`（rec-required を含む）の後に
 * `make seed-recording-one SEEDER=RecordingAgreementMultiSeeder`。
 * 既定の一覧（RecordingSeeder::defaultScenarios()）には入れない。「規約が 1 件」の撮影（ケース 3・4 など）が
 * 意図せず複数件になるのを避けるため、必要なときだけ流す。
 *
 * 冪等性: version をキーに updateOrInsert。何度流しても足すのは 2 行のまま。
 * 副作用: 既存の規約は消さない・変えない。rec- のマーカーなので、RecordingResetSeeder がそのまま消せる。
 * version は varchar(20) 以内（'rec-required-2' は 14 文字）。
 */
class RecordingAgreementMultiSeeder extends RecordingScenarioSeeder
{
    /** 規約本文は撮影中に開かないので、実在しないダミーの URL でよい */
    private const DUMMY_URL = 'https://example.com/recording/terms';

    protected function apply(): void
    {
        $prefix = self::AGREEMENT_VERSION_PREFIX;
        $now = ClockUtility::nowToString();

        // 公開日は過去にして、すぐ pending（未同意の必須規約）に出るようにする
        $agreements = [
            ['version' => $prefix.'required-2', 'title' => '[REC] 利用規約その2（必須）', 'is_required' => true, 'published_at' => '2020-01-01 00:00:00'],
            ['version' => $prefix.'required-3', 'title' => '[REC] 利用規約その3（必須）', 'is_required' => true, 'published_at' => '2020-01-01 00:00:00'],
        ];

        foreach ($agreements as $agreement) {
            DB::connection('sys')->table('sys_agreement')->updateOrInsert(
                ['version' => $agreement['version']],
                $agreement + ['url' => self::DUMMY_URL, 'updated_at' => $now],
            );
        }

        $this->report('必須規約を 2 件足しました（rec-required と合わせて 3 件）。');
    }
}
