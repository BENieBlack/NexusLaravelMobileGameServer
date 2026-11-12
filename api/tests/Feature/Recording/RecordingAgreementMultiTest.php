<?php

namespace Tests\Feature\Recording;

use Database\Seeders\Recording\RecordingSeeder;
use Database\Seeders\Recording\Scenarios\RecordingAgreementMultiSeeder;
use Database\Seeders\Recording\Scenarios\RecordingAgreementSeeder;
use Database\Seeders\Recording\Scenarios\RecordingResetSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * 「規約が複数件」のシナリオ（RecordingAgreementMultiSeeder）のテスト。
 *
 * pending の確認はサインアップ（UseCase が自前でトランザクションを張る）を使うため、
 * RecordingAgreementPendingTest と同じく自動ラップせず、tearDown でマーカー付きの規約だけ消す。
 */
class RecordingAgreementMultiTest extends RecordingTestCase
{
    private string $token;

    public function beginDatabaseTransaction(): void
    {
        // 自動ラップしない（後始末は tearDown）
    }

    protected function setUp(): void
    {
        parent::setUp();

        ['token' => $this->token] = $this->signUpPlayer();
    }

    protected function tearDown(): void
    {
        DB::connection('sys')->table('sys_agreement')->where('version', 'like', 'rec-%')->delete();

        parent::tearDown();
    }

    #[Test]
    public function 必須規約が3件になりpendingに3件出る(): void
    {
        // 前提: 既定のシナリオ（rec-required を含む）の後に、複数件のシナリオを流す
        $this->runSeeder(RecordingAgreementSeeder::class);
        $this->runSeeder(RecordingAgreementMultiSeeder::class);

        // 操作
        $response = $this->getJson('/api/agreement/pending', $this->authHeaders($this->token));

        // 期待: 公開済みの必須 3 件が出る（任意・未公開は出ない）
        $response->assertOk();
        $titles = array_column($response->json('agreement_list'), 'title');
        $this->assertContains('[REC] 利用規約（必須）', $titles);
        $this->assertContains('[REC] 利用規約その2（必須）', $titles);
        $this->assertContains('[REC] 利用規約その3（必須）', $titles);
        $this->assertNotContains('[REC] プライバシー（任意）', $titles);
        $this->assertNotContains('[REC] 改定予定の規約（未公開）', $titles);
    }

    #[Test]
    public function 冪等で2回流しても増えず_versionは20文字以内のマーカー付きになる(): void
    {
        $this->runSeeder(RecordingAgreementMultiSeeder::class);
        $first = $this->countAgreements(markedOnly: true);
        $this->runSeeder(RecordingAgreementMultiSeeder::class);

        $this->assertSame(2, $first);
        $this->assertSame($first, $this->countAgreements(markedOnly: true));
        foreach (DB::connection('sys')->table('sys_agreement')->where('version', 'like', 'rec-%')->pluck('version') as $version) {
            $this->assertLessThanOrEqual(20, strlen((string) $version));
        }
    }

    #[Test]
    public function resetで消え_既定の一覧には入らない(): void
    {
        $this->runSeeder(RecordingAgreementSeeder::class);
        $this->runSeeder(RecordingAgreementMultiSeeder::class);
        $this->assertSame(5, $this->countAgreements(markedOnly: true));

        // 操作: Reset（マーカー付きの規約をまとめて消す）
        $this->runSeeder(RecordingResetSeeder::class);

        // 期待: 5 件とも消える。複数件のシナリオは make seed-recording の既定の一覧に入っていない
        $this->assertSame(0, $this->countAgreements(markedOnly: true));
        $this->assertNotContains(RecordingAgreementMultiSeeder::class, RecordingSeeder::defaultScenarios());
    }
}
