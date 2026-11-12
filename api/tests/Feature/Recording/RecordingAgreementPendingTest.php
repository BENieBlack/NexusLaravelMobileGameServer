<?php

namespace Tests\Feature\Recording;

use Database\Seeders\Recording\Scenarios\RecordingAgreementSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * 動画用の規約が API の pending にどう出るかのテスト。Reset の検証は RecordingScenarioTest（トランザクション内）に置く。
 *
 * サインアップは UseCase が自前でトランザクションを張るため、既存の AgreementEndpointTest と同じく
 * テスト全体をトランザクションで包まず、tearDown で自分の入れた行を消す。
 */
class RecordingAgreementPendingTest extends RecordingTestCase
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
        // 入れたのは規約だけなので、マーカー付きの規約だけ消す（Reset は mst・デプロイも触るため使わない）
        DB::connection('sys')->table('sys_agreement')->where('version', 'like', 'rec-%')->delete();

        parent::tearDown();
    }

    #[Test]
    public function 公開済みの必須規約だけが未同意一覧に出る(): void
    {
        $this->runSeeder(RecordingAgreementSeeder::class);

        $response = $this->getJson('/api/agreement/pending', $this->authHeaders($this->token));

        // 任意（is_required=false）と未来の公開日の規約は出ない
        $response->assertOk();
        $titles = array_column($response->json('agreement_list'), 'title');
        $this->assertContains('[REC] 利用規約（必須）', $titles);
        $this->assertNotContains('[REC] プライバシー（任意）', $titles);
        $this->assertNotContains('[REC] 改定予定の規約（未公開）', $titles);
    }
}
