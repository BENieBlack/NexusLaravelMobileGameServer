<?php

namespace Tests\Feature\Login;

use App\Domain\Diamond\Services\ApiSessionPlatformProvider;
use App\Models\Mst\MstLoginBonus;
use App\Models\Mst\MstLoginBonusContent;
use App\Persistence\ApiSession;
use App\Repositories\Trx\TrxDiamondRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Nexus\Core\Resource\DataTransferObjects\Resource;
use Nexus\Core\Resource\Enums\ResourceType;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use Nexus\Core\ResourceDelivery\Handlers\DiamondDeliveryHandler;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * auth/login のダイヤ（D5）
 *
 * - 応答の trx_diamond は、X-Platform の platform の 1 行だけ（無ければ null）。trx_diamond_list は返さない。
 *   有償だけを持つので free_amount は無い（無償ダイヤは trx_item_list の free_diamond）
 * - 無償ダイヤの付与（ログインボーナス）は trx_item の free_diamond に入り、trx_diamond の行は作らない
 * - 有償ダイヤの付与（PAID_DIAMOND）は、metadata に platform が無ければ X-Platform の行に入る（無ければ Apple）
 * - is_delete は JSON の真偽値（DB から読んだ行も、同じリクエストで作った未保存の行も）
 */
class HomeTrxDiamondTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected function tearDown(): void
    {
        DB::connection('mst')->table('mst_login_bonus_content')->delete();
        DB::connection('mst')->table('mst_login_bonus')->delete();

        parent::tearDown();
    }

    #[Test]
    public function loginのtrx_diamondはx_platformの行だけで_trx_diamond_listは返さない(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->insertDiamond($player->id, 'Apple', 10);
        $this->insertDiamond($player->id, 'Google', 30);

        $apple = $this->login($token, 'Apple');
        $this->assertSame('Apple', $apple->json('trx_diamond.platform'));
        $this->assertSame(10, $apple->json('trx_diamond.paid_amount'));
        $this->assertArrayNotHasKey('free_amount', $apple->json('trx_diamond'));
        $this->assertArrayNotHasKey('trx_diamond_list', $apple->json());

        $google = $this->login($token, 'Google');
        $this->assertSame('Google', $google->json('trx_diamond.platform'));
        $this->assertSame(30, $google->json('trx_diamond.paid_amount'));
        $this->assertArrayNotHasKey('free_amount', $google->json('trx_diamond'));
    }

    #[Test]
    public function その_platformの行が無いときはnullを返す(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->deleteInitialGrant($player->id);

        // 行がまったく無い
        $response = $this->login($token, 'Apple');
        $this->assertArrayHasKey('trx_diamond', $response->json());
        $this->assertNull($response->json('trx_diamond'));

        // 別の platform の行しか無い
        $this->insertDiamond($player->id, 'Apple', 1);
        $this->assertNull($this->login($token, 'Google')->json('trx_diamond'));
    }

    #[Test]
    public function 新規プレイヤーのloginは_sign_upのx_platformの行を返す(): void
    {
        $this->withHeader('X-Platform', 'Google');
        ['token' => $token] = $this->signUpPlayer();

        $response = $this->login($token, 'Google');

        $this->assertSame('Google', $response->json('trx_diamond.platform'));
        $this->assertSame(0, $response->json('trx_diamond.paid_amount'));
        $this->assertArrayNotHasKey('free_amount', $response->json('trx_diamond'));
    }

    #[Test]
    public function ログインボーナスの無償ダイヤはtrx_itemに入りtrx_diamondの行は作らない(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->deleteInitialGrant($player->id);
        $this->insertDiamond($player->id, 'Apple', 7);
        $this->createFreeDiamondLoginBonus(100);

        // Google でログイン。Google の trx_diamond の行は無いので null のまま（無償は trx_item なので行は要らない）
        $response = $this->login($token, 'Google');

        $this->assertNull($response->json('trx_diamond'));
        $freeDiamond = collect($response->json('trx_item_list'))->firstWhere('mst_item_id', 'free_diamond');
        $this->assertSame(100, $freeDiamond['free_amount']);

        // DB: free_diamond の行ができ、trx_diamond は Apple の行のまま（Google の行は作られない）
        $connection = DB::connection($this->playerConnection($player->id));
        $this->assertSame(100, (int) $connection->table('trx_item')
            ->where('sys_player_id', $player->id)->where('mst_item_id', 'free_diamond')->value('free_amount'));
        $this->assertSame(['Apple' => 7], array_map('intval', $connection->table('trx_diamond')
            ->where('sys_player_id', $player->id)->pluck('paid_amount', 'platform')->all()));
    }

    #[Test]
    public function サインアップの50にログインボーナスの無償ダイヤが同じ行へ加算される(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->createFreeDiamondLoginBonus(100);

        $response = $this->login($token, 'Apple');

        // 初期付与 50 + ログインボーナス 100 が trx_item の 1 行に入る
        $freeDiamonds = collect($response->json('trx_item_list'))->where('mst_item_id', 'free_diamond');
        $this->assertCount(1, $freeDiamonds);
        $this->assertSame(150, $freeDiamonds->first()['free_amount']);
        // trx_diamond は X-Platform の 1 行のまま（有償 0）
        $this->assertSame(0, $response->json('trx_diamond.paid_amount'));
        $this->assertSame(1, DB::connection($this->playerConnection($player->id))->table('trx_diamond')
            ->where('sys_player_id', $player->id)->count());
    }

    #[Test]
    public function 有償ダイヤの付与の既定platformはmetadata_現在のplatform_appleの順で決まる(): void
    {
        ['player' => $player] = $this->signUpPlayer();
        $this->deleteInitialGrant($player->id);

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($player->id);

        $handler = app(DiamondDeliveryHandler::class);

        // API 外（ジョブ・CLI 相当。platform が無い）: Apple
        $this->assertFalse(ApiSession::hasPlatform());
        $handler->handle($player->id, $this->content(1));

        // X-Platform が載っている: その値
        ApiSession::setPlatform('Google');
        $handler->handle($player->id, $this->content(10));

        // metadata の platform が最優先
        $handler->handle($player->id, $this->content(100, ['platform' => 'Apple']));

        $rows = app(TrxDiamondRepository::class)->queryOrMemory();
        $amounts = [];
        foreach ($rows as $row) {
            $amounts[$row->getPlatform()] = $row->getPaidAmount();
        }
        $this->assertSame(['Apple' => 101, 'Google' => 10], $amounts);
    }

    #[Test]
    public function api_session_platform_providerは未設定ならnullで例外を投げない(): void
    {
        ApiSession::clearForTest();

        $provider = new ApiSessionPlatformProvider;
        $this->assertNull($provider->findCurrentPlatform());

        ApiSession::setPlatform('Google');
        $this->assertSame('Google', $provider->findCurrentPlatform());

        ApiSession::clearForTest();
        $this->assertNull($provider->findCurrentPlatform());
    }

    #[Test]
    public function is_deleteはloginの全行で真偽値になる(): void
    {
        ['token' => $token] = $this->signUpPlayer();

        $login = $this->login($token, 'Apple');
        $this->assertFalse($login->json('trx_diamond.is_delete'));
        $this->assertFalse($login->json('trx_wallet_list.0.is_delete'));
        $this->assertFalse($login->json('trx_item_list.0.is_delete'));
        $this->assertFalse($login->json('trx_stamina_list.0.is_delete'));

        // 応答の JSON 全体に 0/1 の is_delete が残っていない
        $this->assertStringNotContainsString('"is_delete":0', $login->getContent());
        $this->assertStringNotContainsString('"is_delete":1', $login->getContent());
    }

    private function login(string $token, string $platform): TestResponse
    {
        // 同一プロセスで前のリクエストの platform を読まないよう、リクエストの間で空にする
        ApiSession::clearForTest();

        $response = $this->withHeaders($this->authHeaders($token) + ['X-Platform' => $platform])
            ->postJson('/api/auth/login');
        $response->assertOk();

        return $response;
    }

    private function insertDiamond(int $sysPlayerId, string $platform, int $paid): void
    {
        DB::connection($this->playerConnection($sysPlayerId))->table('trx_diamond')->updateOrInsert(
            ['sys_player_id' => $sysPlayerId, 'platform' => $platform],
            [
                'paid_amount' => $paid,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /** 初回ログインで 1 日目に無償ダイヤ（item / free_diamond）を配る日次ログインボーナスを用意する */
    private function createFreeDiamondLoginBonus(int $amount): void
    {
        MstLoginBonus::create(['id' => 'daily_login', 'type' => 'daily', 'loop_days' => 7, 'is_active' => true]);
        MstLoginBonusContent::create([
            'mst_login_bonus_id' => 'daily_login',
            'day' => 1,
            'content_type' => 'item',
            'content_mst_id' => 'free_diamond',
            'amount' => $amount,
            'is_paid' => false,
            'sort_order' => 1,
        ]);
        $this->refreshMstCache();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function content(int $amount, array $metadata = []): ResourceDeliveryContent
    {
        return new ResourceDeliveryContent(new Resource(ResourceType::PAID_DIAMOND, 'paid_diamond', $amount, null, $metadata ?: null));
    }
}
