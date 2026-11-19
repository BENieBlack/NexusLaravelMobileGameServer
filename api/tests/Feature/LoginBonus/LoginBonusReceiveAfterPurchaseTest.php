<?php

namespace Tests\Feature\LoginBonus;

use App\Exceptions\GameErrorCode;
use App\Persistence\ApiSession;
use App\Repositories\Sys\SysPlayerRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 課金（in_app_purchase/buy）と VIP の再受け取り（login_bonus/receive）を同じプレイヤーで続けて呼ぶテスト
 *
 * 同時実行そのもの（player.lock の直列化）はミドルウェアのテストに任せ、ここでは
 * 「課金が先に終わっていれば、上がったレベルで receive が通る」「課金の前の receive は 26001」
 * という、直列にしたときの結果を実際の経路で固定する（sequence.md 3.5 節）。
 */
class LoginBonusReceiveAfterPurchaseTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const DEPLOY_KEY = 202601010;

    /** 購入で付与される VIP ポイント（テスト用のレベル表で 1 以上に上がる量） */
    private const PURCHASE_VIP_POINT = 5000;

    private string $accessToken;

    private int $sysPlayerId;

    private string $connection;

    private string $transactionId;

    public function beginDatabaseTransaction(): void
    {
        // UseCase が自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactionId = 'GPA.LBPURCHASE-'.Str::random(10);
        ClockUtility::setDayStartTime('04:00:00');
        ClockUtility::setNow('2026-10-15 12:00:00');

        $this->cleanUpMaster();
        $this->createMaster();
        $this->refreshMstCache();
        $this->fakeGooglePlay();
        config([
            'services.google_play.package_name' => 'com.example.nexus',
            'services.google_play.service_account' => json_encode([
                'client_email' => 'nexus@example.iam.gserviceaccount.com', // private-info:allow（ダミーのサービスアカウント）
                'private_key' => $this->generatePrivateKey(),
            ]),
        ]);

        ['player' => $player, 'token' => $this->accessToken] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        $this->connection = $this->playerConnection($this->sysPlayerId);
        $this->deleteInitialGrant($this->sysPlayerId);
    }

    protected function tearDown(): void
    {
        foreach (['trx_vip_login_bonus', 'trx_item', 'trx_diamond', 'trx_in_app_purchase', 'log_action_vip_login_bonus_receive'] as $table) {
            DB::connection($this->connection)->table($table)->where('sys_player_id', $this->sysPlayerId)->delete();
        }
        $this->cleanUpMaster();
        $this->refreshMstCache();
        ApiSession::clearForTest();
        ClockUtility::reset();
        ClockUtility::setDayStartTime('00:00:00');

        parent::tearDown();
    }

    #[Test]
    public function 課金が先に終わっていれば_上がったレベルでreceiveが通る(): void
    {
        $this->login()->assertOk();
        $levelBefore = $this->listCalendar()->json('vip_login_bonus_calendar.vip_level');
        $this->assertFalse($this->listCalendar()->json('vip_login_bonus_calendar.can_re_receive'));

        $this->buy()->assertOk();

        // 課金でレベルが上がり、再受け取りが可能になる
        $vip = $this->listCalendar()->json('vip_login_bonus_calendar');
        $this->assertGreaterThan($levelBefore, $vip['vip_level']);
        $this->assertTrue($vip['can_re_receive']);

        $receive = $this->receive($vip['mst_vip_login_bonus_id']);
        $receive->assertOk();
        $this->assertSame($vip['vip_level'], $receive->json('trx_vip_login_bonus.vip_level'));
    }

    #[Test]
    public function 課金より前のreceiveは26001で_課金の後の古い_i_dは26002(): void
    {
        $this->login()->assertOk();
        $oldVip = $this->listCalendar()->json('vip_login_bonus_calendar');

        // 課金の前: レベルがまだ上がっていない
        $this->assertBusinessError($this->receive($oldVip['mst_vip_login_bonus_id']), GameErrorCode::LOGIN_BONUS_RE_RECEIVE_NOT_AVAILABLE);

        $this->buy()->assertOk();

        // 課金の後に旧レベルの ID を送ると、今のレベルの ID と一致しないので 26002
        $this->assertBusinessError($this->receive($oldVip['mst_vip_login_bonus_id']), GameErrorCode::LOGIN_BONUS_MASTER_NOT_FOUND);
    }

    // ------------------------------------------------------------------

    private function headers(array $extra = []): array
    {
        return [...$this->authHeaders($this->accessToken), 'X-Platform' => 'Google', ...$extra];
    }

    private function freshRequest(): void
    {
        ApiSession::clearForTest();
        app(QueryManager::class)->clear();
        app(SysPlayerRepository::class)->forgetCachedModels();
        // テストでは Route が最初のリクエストの Controller を使い回し、その中の UseCase が前のリクエストの Repository
        // （読んだ sys_player のモデルを持つ）を握り続ける。本番はリクエストごとにプロセスが新しいので、同じ状態に揃える
        foreach (app('router')->getRoutes() as $route) {
            $route->flushController();
        }
    }

    private function login(): TestResponse
    {
        $this->freshRequest();

        return $this->postJson('/api/auth/login', [], $this->headers());
    }

    private function listCalendar(): TestResponse
    {
        $this->freshRequest();

        return $this->postJson('/api/login_bonus/list', [], $this->headers());
    }

    private function receive(string $id): TestResponse
    {
        $this->freshRequest();

        return $this->postJson('/api/login_bonus/receive', ['mst_vip_login_bonus_id' => $id], $this->headers());
    }

    private function buy(): TestResponse
    {
        $this->freshRequest();

        return $this->postJson('/api/in_app_purchase/buy', [
            'mst_in_app_purchase_id' => (int) DB::connection('mst')->table('mst_in_app_purchase')->value('id'),
            'platform' => 'Google',
            'billing_platform' => 'google_play',
            'receipt' => 'purchase-token-lb',
            'transaction_id' => $this->transactionId,
            'product_id' => 'diamond_lb',
        ], $this->headers());
    }

    private function assertBusinessError(TestResponse $response, int $errorCode): void
    {
        $response->assertStatus(299);
        $this->assertSame($errorCode, $response->json('error_code'));
    }

    private function fakeGooglePlay(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'androidpublisher.googleapis.com/*' => Http::response([
                'purchaseState' => 0,
                'consumptionState' => 0,
                'orderId' => $this->transactionId,
                'purchaseTimeMillis' => '1755648000000',
                'quantity' => 1,
                'priceAmountMicros' => '490000000',
                'priceCurrencyCode' => 'JPY',
            ]),
        ]);
    }

    /** VIP は全レベル（0〜20）に 3 日ループ、課金の商品は VIP ポイントを付けるダイヤ 1 つ */
    private function createMaster(): void
    {
        for ($level = 0; $level <= 20; $level++) {
            DB::connection('mst')->table('mst_vip_login_bonus')->insert([
                'id' => "lbp_vip_lv{$level}", 'vip_level' => $level, 'loop_days' => 3, 'is_active' => true,
            ]);
            for ($day = 1; $day <= 3; $day++) {
                DB::connection('mst')->table('mst_vip_login_bonus_content')->insert([
                    'mst_vip_login_bonus_id' => "lbp_vip_lv{$level}", 'day' => $day,
                    'content_type' => 'item', 'content_mst_id' => 'vip_item',
                    'content_quantity' => $level * 100 + $day, 'amount' => 1,
                ]);
            }
        }

        $platformProductId = DB::connection('mst')->table('mst_billing_platform_product')->insertGetId([
            'deploy_key' => self::DEPLOY_KEY, 'platform_product_id' => 'diamond_lb', 'billing_platform' => 'google_play',
            'product_type' => 'consumable', 'price_amount_micros' => 490_000_000, 'price_currency_code' => 'JPY',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('mst')->table('mst_in_app_purchase')->insert([
            'deploy_key' => self::DEPLOY_KEY, 'type' => 'diamond', 'paid_diamond_amount' => 500,
            'vip_point' => self::PURCHASE_VIP_POINT, 'purchase_limit_reset' => 'none',
            'app_store_product_id' => null, 'google_play_product_id' => $platformProductId,
            'sort_desc' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function cleanUpMaster(): void
    {
        foreach (['mst_vip_login_bonus_content', 'mst_vip_login_bonus', 'mst_in_app_purchase', 'mst_billing_platform_product'] as $table) {
            DB::connection('mst')->table($table)->delete();
        }
    }

    private function generatePrivateKey(): string
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $privateKey);

        return $privateKey;
    }
}
