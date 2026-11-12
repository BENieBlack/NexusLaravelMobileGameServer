<?php

namespace Database\Seeders\Recording\Scenarios;

use Database\Seeders\Recording\RecordingScenarioSeeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;

/**
 * RecordingAgreementSeeder
 *
 * 目的: 規約画面の確認用に、規約（sys_agreement）を3件用意する。
 * - rec-required: 公開済みの必須規約（pending に出る）
 * - rec-optional: 公開済みの任意規約（is_required=false。pending は必須だけを返すので出ない）
 * - rec-future  : 公開日が未来の必須規約（公開前なので表示されない）
 *
 * 使うケース（client_design.md 7節）: 3（規約あり・同意）、4（同意済みの2回目）、
 * 12・13（401 → refresh の確認）、15（二重送信）。
 * ケース 1・2 の「規約なし」は、このシナリオを流す前、または RecordingResetSeeder の後に撮る。
 *
 * 冪等性: version をキーに updateOrInsert。何度流しても3行のまま。
 * 副作用: 既存の規約は消さない・変えない。ただし他の必須規約が既にあると、
 * それも pending に出る（「規約なし」の撮影前に確認すること）。
 */
class RecordingAgreementSeeder extends RecordingScenarioSeeder
{
    /** 規約本文は撮影中に開かないので、実在しないダミーの URL でよい */
    private const DUMMY_URL = 'https://example.com/recording/terms';

    protected function apply(): void
    {
        $prefix = self::AGREEMENT_VERSION_PREFIX;
        $now = ClockUtility::nowToString();

        $agreements = [
            ['version' => $prefix.'required', 'title' => '[REC] 利用規約（必須）', 'is_required' => true, 'published_at' => '2020-01-01 00:00:00'],
            ['version' => $prefix.'optional', 'title' => '[REC] プライバシー（任意）', 'is_required' => false, 'published_at' => '2020-01-01 00:00:00'],
            // 公開日を遠い未来にして「公開前は表示されない」ことを確かめる
            ['version' => $prefix.'future', 'title' => '[REC] 改定予定の規約（未公開）', 'is_required' => true, 'published_at' => '2099-01-01 00:00:00'],
        ];

        foreach ($agreements as $agreement) {
            DB::connection('sys')->table('sys_agreement')->updateOrInsert(
                ['version' => $agreement['version']],
                $agreement + ['url' => self::DUMMY_URL, 'updated_at' => $now],
            );
        }

        $this->report('規約を3件用意しました（必須・任意・未公開）。');
    }
}
