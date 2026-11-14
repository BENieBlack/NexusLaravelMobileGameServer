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
 * - 応答の trx_diamond は、X-Platform の platform の 1 行だけ（無ければ null）。trx_diamond_list は返さない
 * - 無償ダイヤの付与は、metadata に platform が無ければ X-Platform の行に入る（無ければ Apple）
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
        $this->insertDiamond($player->id, 'Apple', 10, 20);
        $this->insertDiamond($player->id, 'Google', 30, 40);

        $apple = $this->login($token, 'Apple');
        $this->assertSame('Apple', $apple->json('trx_diamond.platform'));
        $this->assertSame(10, $apple->json('trx_diamond.paid_amount'));
        $this->assertSame(20, $apple->json('trx_diamond.free_amount'));
        $this->assertArrayNotHasKey('trx_diamond_list', $apple->json());

        $google = $this->login($token, 'Google');
        $this->assertSame('Google', $google->json('trx_diamond.platform'));
        $this->assertSame(30, $google->json('trx_diamond.paid_amount'));
        $this->assertSame(40, $google->json('trx_diamond.free_amount'));
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
        $this->insertDiamond($player->id, 'Apple', 1, 2);
        $this->assertNull($this->login($token, 'Google')->json('trx_diamond'));
    }

    #[Test]
    public function 新規プレイヤーのloginは_sign_upのx_platformの行を返す(): void
    {
        $this->withHeader('X-Platform', 'Google');
        ['token' => $token] = $this->signUpPlayer();

        $response = $this->login($token, 'Google');

        $this->assertSame('Google', $response->json('trx_diamond.platform'));
        $this->assertSame(0, $response->json('trx_diamond.free_amount'));
    }

    #[Test]
    public function googleでログインするとappleの行だけがある状態でもログインボーナスの無償ダイヤでgoogleの行が作られ同じ応答に載る(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->deleteInitialGrant($player->id);
        $this->insertDiamond($player->id, 'Apple', 0, 5);
        $this->createDiamondLoginBonus(100);

        $response = $this->login($token, 'Google');

        $this->assertSame('Google', $response->json('trx_diamond.platform'));
        $this->assertSame(100, $response->json('trx_diamond.free_amount'));
        // 未保存の新規行でも is_delete は JSON の真偽値（実測）
        $this->assertFalse($response->json('trx_diamond.is_delete'));
        $this->assertStringContainsString('"is_delete":false', $response->getContent());

        // DB: Google の行が作られ、Apple の行は変わらない
        $rows = DB::connection($this->playerConnection($player->id))->table('trx_diamond')
            ->where('sys_player_id', $player->id)->pluck('free_amount', 'platform')->all();
        $this->assertSame(['Apple' => 5, 'Google' => 100], array_map('intval', $rows));
    }

    #[Test]
    public function appleでログインするとログインボーナスの無償ダイヤはappleの行に入る(): void
    {
        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->createDiamondLoginBonus(100);

        $response = $this->login($token, 'Apple');

        $this->assertSame('Apple', $response->json('trx_diamond.platform'));
        $this->assertSame(100, $response->json('trx_diamond.free_amount'));
        $this->assertSame(1, DB::connection($this->playerConnection($player->id))->table('trx_diamond')
            ->where('sys_player_id', $player->id)->count());
    }

    #[Test]
    public function 付与の既定platformはmetadata_現在のplatform_appleの順で決まる(): void
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
            $amounts[$row->getPlatform()] = $row->getFreeAmount();
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

    private function insertDiamond(int $sysPlayerId, string $platform, int $paid, int $free): void
    {
        DB::connection($this->playerConnection($sysPlayerId))->table('trx_diamond')->updateOrInsert(
            ['sys_player_id' => $sysPlayerId, 'platform' => $platform],
            [
                'paid_amount' => $paid,
                'free_amount' => $free,
                'is_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /** 初回ログインで 1 日目に無償ダイヤを配る日次ログインボーナスを用意する */
    private function createDiamondLoginBonus(int $amount): void
    {
        MstLoginBonus::create(['id' => 'daily_login', 'type' => 'daily', 'loop_days' => 7, 'is_active' => true]);
        MstLoginBonusContent::create([
            'mst_login_bonus_id' => 'daily_login',
            'day' => 1,
            'content_type' => 'diamond',
            'content_mst_id' => 'diamond',
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
        return new ResourceDeliveryContent(new Resource(ResourceType::DIAMOND, 'diamond', $amount, null, $metadata ?: null));
    }
}
