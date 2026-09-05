<?php

namespace Tests\Feature\RewardTrack;

use App\Exceptions\GameErrorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * RewardTrack（バトルパス型報酬トラック）のエンドポイントのテスト
 *
 * 進捗・所持ライン・受け取り済みの3つを突き合わせて配布可否を決めるため、
 * ユニットテストのモックだけでは接続先（mstとtrxシャード）の食い違いを拾えない。
 * ここは実際にHTTPで叩いて、DBの状態まで見る。
 */
class RewardTrackEndpointTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const TRACK_ID = 'bp_test';

    private const FREE_LINE_ID = 'bp_test_free';

    private const PAID_LINE_ID = 'bp_test_paid';

    private const MILESTONE_10 = 'bp_test_ms_10';

    private const MILESTONE_20 = 'bp_test_ms_20';

    private int $sysPlayerId;

    private string $token;

    private string $connection;

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
        $this->connection = $this->playerConnection($this->sysPlayerId);

        $this->cleanUpMaster();
        $this->makeMaster();
    }

    protected function tearDown(): void
    {
        foreach (['trx_reward_track', 'trx_reward_track_line', 'trx_reward_track_milestone', 'trx_wallet'] as $table) {
            DB::connection($this->connection)->table($table)
                ->where('sys_player_id', $this->sysPlayerId)->delete();
        }
        $this->cleanUpMaster();

        parent::tearDown();
    }

    // =========================================================
    // サマリー
    // =========================================================

    #[Test]
    public function 進捗が無くてもサマリーを取得できる(): void
    {
        $response = $this->getJson(
            '/api/reward-track/summary?mst_reward_track_id='.self::TRACK_ID,
            $this->authHeaders($this->token)
        );

        $response->assertOk();
        $this->assertSame(0, $response->json('current_progress'));
        // 無料ラインは購入行が無くても常に所持扱いになる
        $this->assertContains(self::FREE_LINE_ID, $response->json('owned_line_id_list'));
        $this->assertNotContains(self::PAID_LINE_ID, $response->json('owned_line_id_list'));
    }

    #[Test]
    public function サマリーに進捗とマイルストーンが入る(): void
    {
        $this->makeProgress(15);

        $response = $this->getJson(
            '/api/reward-track/summary?mst_reward_track_id='.self::TRACK_ID,
            $this->authHeaders($this->token)
        );

        $response->assertOk();
        $this->assertSame(15, $response->json('current_progress'));
        $this->assertCount(2, $response->json('milestones'));
        $this->assertSame(self::MILESTONE_10, $response->json('milestones.0.id'));
        // マイルストーンにはライン別の報酬がぶら下がる
        $this->assertArrayHasKey(self::FREE_LINE_ID, $response->json('milestones.0.contents'));
        $this->assertArrayHasKey(self::PAID_LINE_ID, $response->json('milestones.0.contents'));
    }

    #[Test]
    public function 購入したラインはサマリーの所持ラインに入る(): void
    {
        $this->makeOwnedLine(self::PAID_LINE_ID);

        $response = $this->getJson(
            '/api/reward-track/summary?mst_reward_track_id='.self::TRACK_ID,
            $this->authHeaders($this->token)
        );

        $response->assertOk();
        $this->assertContains(self::PAID_LINE_ID, $response->json('owned_line_id_list'));
    }

    #[Test]
    public function 受け取り済みはサマリーに残る(): void
    {
        $this->makeProgress(15);
        $this->receive(self::MILESTONE_10, self::FREE_LINE_ID)->assertOk();

        $response = $this->getJson(
            '/api/reward-track/summary?mst_reward_track_id='.self::TRACK_ID,
            $this->authHeaders($this->token)
        );

        $response->assertOk();
        $this->assertNotEmpty($response->json('received_key_set'));
    }

    #[Test]
    public function 存在しないトラックのサマリーは業務エラーで返る(): void
    {
        $response = $this->getJson(
            '/api/reward-track/summary?mst_reward_track_id=not_exist',
            $this->authHeaders($this->token)
        );

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::REWARD_TRACK_NOT_FOUND, $response->json('error_code'));
    }

    #[Test]
    public function 認証なしではサマリーを取得できない(): void
    {
        $this->getJson('/api/reward-track/summary?mst_reward_track_id='.self::TRACK_ID)
            ->assertUnauthorized();
    }

    // =========================================================
    // 受け取り
    // =========================================================

    #[Test]
    public function 進捗を満たした無料ラインの報酬を受け取れる(): void
    {
        $this->makeProgress(10);

        $response = $this->receive(self::MILESTONE_10, self::FREE_LINE_ID);

        $response->assertOk();
        $this->assertSame(self::MILESTONE_10, $response->json('mst_reward_track_milestone_id'));
        $this->assertSame(self::FREE_LINE_ID, $response->json('mst_reward_track_line_id'));
        $this->assertSame(1, $this->countReceipts());
        // content_quantity(100) × amount(1)
        $this->assertSame(100, $this->findWalletAmount('gold'));
    }

    #[Test]
    public function 購入済みの有料ラインの報酬を受け取れる(): void
    {
        $this->makeProgress(10);
        $this->makeOwnedLine(self::PAID_LINE_ID);

        $this->receive(self::MILESTONE_10, self::PAID_LINE_ID)->assertOk();

        // content_quantity(150) × amount(2)
        $this->assertSame(300, $this->findWalletAmount('gold'));
    }

    #[Test]
    public function 同じ報酬は二重に受け取れない(): void
    {
        $this->makeProgress(10);
        $this->receive(self::MILESTONE_10, self::FREE_LINE_ID)->assertOk();

        $response = $this->receive(self::MILESTONE_10, self::FREE_LINE_ID);

        // 業務エラーなので299。500だとクライアントが障害と区別できない
        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::REWARD_TRACK_ALREADY_RECEIVED, $response->json('error_code'));
        $this->assertSame(1, $this->countReceipts(), '履歴は増えない');
        $this->assertSame(100, $this->findWalletAmount('gold'), '報酬も増えない');
    }

    #[Test]
    public function 進捗が足りないマイルストーンは受け取れない(): void
    {
        $this->makeProgress(10);

        $response = $this->receive(self::MILESTONE_20, self::FREE_LINE_ID);

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::REWARD_TRACK_PROGRESS_NOT_ENOUGH, $response->json('error_code'));
        $this->assertSame(0, $this->countReceipts());
        $this->assertSame(0, $this->findWalletAmount('gold'));
    }

    #[Test]
    public function 購入していない有料ラインの報酬は受け取れない(): void
    {
        $this->makeProgress(10);

        $response = $this->receive(self::MILESTONE_10, self::PAID_LINE_ID);

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::REWARD_TRACK_LINE_NOT_OWNED, $response->json('error_code'));
        $this->assertSame(0, $this->countReceipts());
        $this->assertSame(0, $this->findWalletAmount('gold'));
    }

    #[Test]
    public function 認証なしでは受け取れない(): void
    {
        $this->postJson('/api/reward-track/receive', [
            'mst_reward_track_milestone_id' => self::MILESTONE_10,
            'mst_reward_track_line_id' => self::FREE_LINE_ID,
        ])->assertUnauthorized();

        $this->assertSame(0, $this->countReceipts());
    }

    #[Test]
    public function 必須パラメータが無いリクエストは弾く(): void
    {
        $this->postJson('/api/reward-track/receive', [], $this->authHeaders($this->token))
            ->assertUnprocessable();
    }

    // =========================================================
    // ヘルパ
    // =========================================================

    private function receive(string $milestoneId, string $lineId): TestResponse
    {
        return $this->postJson('/api/reward-track/receive', [
            'mst_reward_track_milestone_id' => $milestoneId,
            'mst_reward_track_line_id' => $lineId,
        ], $this->authHeaders($this->token));
    }

    private function countReceipts(): int
    {
        return DB::connection($this->connection)
            ->table('trx_reward_track_milestone')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('is_delete', false)
            ->count();
    }

    private function findWalletAmount(string $mstItemId): int
    {
        $row = DB::connection($this->connection)
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        return $row ? (int) $row->free_amount + (int) $row->paid_amount : 0;
    }

    private function makeProgress(int $progress): void
    {
        DB::connection($this->connection)->table('trx_reward_track')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'mst_reward_track_id' => self::TRACK_ID,
            'current_progress' => $progress,
            'is_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeOwnedLine(string $lineId): void
    {
        DB::connection($this->connection)->table('trx_reward_track_line')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'mst_reward_track_line_id' => $lineId,
            'mst_in_app_purchase_id' => 501,
            'purchased_at' => now(),
            'is_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeMaster(): void
    {
        DB::connection('mst')->table('mst_reward_track')->insert([
            'id' => self::TRACK_ID,
            'progress_type' => 'point',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(30),
            'is_active' => true,
            'sort_desc' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('mst')->table('mst_reward_track_line')->insert([
            [
                'id' => self::FREE_LINE_ID,
                'mst_reward_track_id' => self::TRACK_ID,
                'is_free' => true,
                'mst_in_app_purchase_id' => null,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => self::PAID_LINE_ID,
                'mst_reward_track_id' => self::TRACK_ID,
                'is_free' => false,
                'mst_in_app_purchase_id' => 501,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::connection('mst')->table('mst_reward_track_milestone')->insert([
            [
                'id' => self::MILESTONE_10,
                'mst_reward_track_id' => self::TRACK_ID,
                'required_progress' => 10,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => self::MILESTONE_20,
                'mst_reward_track_id' => self::TRACK_ID,
                'required_progress' => 20,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::connection('mst')->table('mst_reward_track_content')->insert([
            [
                'mst_reward_track_milestone_id' => self::MILESTONE_10,
                'mst_reward_track_line_id' => self::FREE_LINE_ID,
                'content_type' => 'gold',
                'content_mst_id' => 'gold',
                'content_option' => null,
                'content_quantity' => 100,
                'amount' => 1,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'mst_reward_track_milestone_id' => self::MILESTONE_10,
                'mst_reward_track_line_id' => self::PAID_LINE_ID,
                'content_type' => 'gold',
                'content_mst_id' => 'gold',
                'content_option' => null,
                'content_quantity' => 150,
                'amount' => 2,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->refreshMstCache();
    }

    private function cleanUpMaster(): void
    {
        DB::connection('mst')->table('mst_reward_track_content')
            ->whereIn('mst_reward_track_milestone_id', [self::MILESTONE_10, self::MILESTONE_20])->delete();
        DB::connection('mst')->table('mst_reward_track_milestone')
            ->where('mst_reward_track_id', self::TRACK_ID)->delete();
        DB::connection('mst')->table('mst_reward_track_line')
            ->where('mst_reward_track_id', self::TRACK_ID)->delete();
        DB::connection('mst')->table('mst_reward_track')->where('id', self::TRACK_ID)->delete();

        $this->refreshMstCache();
    }
}
