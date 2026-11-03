<?php

namespace Tests\Feature\Agreement;

use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * Agreementエンドポイントのテスト
 *
 * 利用規約の未同意一覧取得・同意記録のエンドポイントを固定する。
 * 規約はsysDBで管理し、同意記録はプレイヤーのシャード（trxDB）に入る。
 */
class AgreementEndpointTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    private string $token;

    /** setUp で挿入した sys_agreement の ID 一覧（tearDown で削除） */
    private array $insertedAgreementIds = [];

    public function beginDatabaseTransaction(): void
    {
        // UseCaseが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        $this->token = $token;
    }

    protected function tearDown(): void
    {
        // 挿入した sys_agreement を削除（AUTO_INCREMENT が無限に増えないよう）
        if (! empty($this->insertedAgreementIds)) {
            DB::connection('sys')->table('sys_agreement')
                ->whereIn('id', $this->insertedAgreementIds)
                ->delete();
        }

        // trx_agreement も削除
        $connection = $this->playerConnection($this->sysPlayerId);
        DB::connection($connection)->table('trx_agreement')
            ->where('sys_player_id', $this->sysPlayerId)
            ->delete();

        parent::tearDown();
    }

    #[Test]
    public function 未同意の必須規約一覧を取得できる(): void
    {
        $this->makeAgreement(version: '1.0', title: '利用規約v1.0', isRequired: true, publishedAt: '2020-01-01 00:00:00');

        $response = $this->getJson('/api/agreement/pending', $this->authHeaders($this->token));

        $response->assertOk();
        $this->assertNotEmpty($response->json('agreement_list'));
        $this->assertSame('利用規約v1.0', $response->json('agreement_list.0.title'));
        $this->assertSame('2020-01-01 00:00:00', $response->json('agreement_list.0.published_at'));
    }

    #[Test]
    public function 同意済みの規約は未同意一覧に含まれない(): void
    {
        $agreementId = $this->makeAgreement(version: '1.0', title: '利用規約v1.0', isRequired: true, publishedAt: '2020-01-01 00:00:00');

        // 同意記録を挿入する
        $connection = $this->playerConnection($this->sysPlayerId);
        DB::connection($connection)->table('trx_agreement')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'sys_agreement_id' => $agreementId,
            'consented_at' => ClockUtility::nowToString(),
            'created_at' => ClockUtility::nowToString(),
        ]);

        $response = $this->getJson('/api/agreement/pending', $this->authHeaders($this->token));

        $response->assertOk();
        // 同意済みなので agreement_list には含まれない
        $ids = array_column($response->json('agreement_list'), 'sys_agreement_id');
        $this->assertNotContains($agreementId, $ids);
    }

    #[Test]
    public function 未来の公開日の規約は未同意一覧に含まれない(): void
    {
        $this->makeAgreement(version: '2.0', title: '利用規約v2.0（未来）', isRequired: true, publishedAt: '2099-12-31 00:00:00');

        $response = $this->getJson('/api/agreement/pending', $this->authHeaders($this->token));

        $response->assertOk();
        $titles = array_column($response->json('agreement_list'), 'title');
        $this->assertNotContains('利用規約v2.0（未来）', $titles);
    }

    #[Test]
    public function is_required_falseの規約は未同意一覧に含まれない(): void
    {
        $this->makeAgreement(version: '1.0', title: '任意規約', isRequired: false, publishedAt: '2020-01-01 00:00:00');

        $response = $this->getJson('/api/agreement/pending', $this->authHeaders($this->token));

        $response->assertOk();
        $titles = array_column($response->json('agreement_list'), 'title');
        $this->assertNotContains('任意規約', $titles);
    }

    #[Test]
    public function 未同意規約がない場合は空配列を返す(): void
    {
        // sys_agreement に何も挿入しない状態でリクエストする
        $response = $this->getJson('/api/agreement/pending', $this->authHeaders($this->token));

        $response->assertOk();
        $this->assertSame([], $response->json('agreement_list'));
    }

    #[Test]
    public function 規約に同意できる(): void
    {
        $agreementId = $this->makeAgreement(version: '1.0', title: '利用規約v1.0', isRequired: true, publishedAt: '2020-01-01 00:00:00');

        $response = $this->postJson(
            '/api/agreement/consent',
            ['sys_agreement_id' => $agreementId],
            $this->authHeaders($this->token)
        );

        $response->assertOk();

        // trx_agreement にレコードが作成されていることを確認する
        $connection = $this->playerConnection($this->sysPlayerId);
        $count = DB::connection($connection)->table('trx_agreement')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('sys_agreement_id', $agreementId)
            ->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function 同意レスポンスは空の_jso_nオブジェクト(): void
    {
        $agreementId = $this->makeAgreement(version: '1.0', title: '利用規約v1.0', isRequired: true, publishedAt: '2020-01-01 00:00:00');

        $response = $this->postJson(
            '/api/agreement/consent',
            ['sys_agreement_id' => $agreementId],
            $this->authHeaders($this->token)
        );

        $response->assertOk();
        $this->assertSame([], $response->json());
    }

    #[Test]
    public function 存在しない規約_i_dには同意できない(): void
    {
        $nonExistentId = $this->nonExistentId('sys_agreement', 'sys');

        $response = $this->postJson(
            '/api/agreement/consent',
            ['sys_agreement_id' => $nonExistentId],
            $this->authHeaders($this->token)
        );

        $response->assertStatus(299);
        $this->assertSame(24001, $response->json('error_code'));
    }

    #[Test]
    public function 既に同意済みの規約への同意はエラー(): void
    {
        $agreementId = $this->makeAgreement(version: '1.0', title: '利用規約v1.0', isRequired: true, publishedAt: '2020-01-01 00:00:00');

        // 1回目の同意
        $this->postJson(
            '/api/agreement/consent',
            ['sys_agreement_id' => $agreementId],
            $this->authHeaders($this->token)
        )->assertOk();

        // 2回目の同意 → エラー
        $response = $this->postJson(
            '/api/agreement/consent',
            ['sys_agreement_id' => $agreementId],
            $this->authHeaders($this->token)
        );

        $response->assertStatus(299);
        $this->assertSame(24002, $response->json('error_code'));
    }

    #[Test]
    public function 認証なしでは規約を扱えない(): void
    {
        $this->getJson('/api/agreement/pending')->assertUnauthorized();
        $this->postJson('/api/agreement/consent', ['sys_agreement_id' => 1])->assertUnauthorized();
    }

    /**
     * sys_agreement にテスト用の規約レコードを挿入して ID を返す
     */
    private function makeAgreement(
        string $version,
        string $title,
        bool $isRequired,
        string $publishedAt,
        string $url = 'https://example.com/terms',
    ): int {
        $id = DB::connection('sys')->table('sys_agreement')->insertGetId([
            'version' => $version,
            'title' => $title,
            'url' => $url,
            'is_required' => $isRequired,
            'published_at' => $publishedAt,
            'created_at' => ClockUtility::nowToString(),
            'updated_at' => ClockUtility::nowToString(),
        ]);

        $this->insertedAgreementIds[] = $id;

        return $id;
    }
}
