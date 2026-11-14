<?php

namespace Tests\Feature\Item;

use App\Domain\Stamina\Constants\StaminaConst;
use App\Exceptions\GameErrorCode;
use App\Persistence\ApiSession;
use Database\Seeders\MstItemSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * アイテム使用エンドポイントのテスト
 *
 * POST /api/item/use の応答の形（キー）と、使った分だけ効果が出てアイテムが減ることを固定する。
 * 効果ごとの計算は Tests\Feature\Domain\Item\UseItemTest が UseCase で確かめている。
 */
class ItemEndpointTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const STAMINA_ITEM_ID = 'stamina_potion_endpoint_test';

    private int $sysPlayerId;

    private string $accessToken;

    private string $connection;

    /** MstItemSeeder を流したテストだけ true。tearDown でシーダーの行（mst_item・mst_item__l10n）を消す */
    private bool $seededItems = false;

    public function beginDatabaseTransaction(): void
    {
        // UseCaseが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUpMaster();
        $this->createMaster();
        $this->refreshMstCache();

        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        $this->accessToken = $token;
        $this->connection = $this->playerConnection($this->sysPlayerId);

        $this->createPlayerData();
        ApiSession::clearForTest();
        app(QueryManager::class)->clear();
    }

    protected function tearDown(): void
    {
        foreach (['trx_item', 'trx_stamina'] as $table) {
            DB::connection($this->connection)->table($table)
                ->where('sys_player_id', $this->sysPlayerId)->delete();
        }

        if ($this->seededItems) {
            DB::connection('mst')->table('mst_item')->delete();
            DB::connection('mst')->table('mst_item__l10n')->delete();
        }

        $this->cleanUpMaster();
        $this->refreshMstCache();
        ApiSession::clearForTest();

        parent::tearDown();
    }

    #[Test]
    public function 使ったアイテムと効果を返す(): void
    {
        $response = $this->useItem(self::STAMINA_ITEM_ID, 2);

        $response->assertOk();
        $response->assertJson([
            'mst_item_id' => self::STAMINA_ITEM_ID,
            'effect' => 'stamina_recover',
            'item_used' => 2,
            'applied_value' => 60,
        ]);

        // 減ったアイテムが、更新された trx として返る（10個 - 2個）
        $trxItems = $response->json('updated_resources.trx_item_list');
        $this->assertCount(1, $trxItems);
        $this->assertSame(self::STAMINA_ITEM_ID, $trxItems[0]['mst_item_id']);
        $this->assertSame(8, $trxItems[0]['free_amount']);
    }

    #[Test]
    public function 使った分だけアイテムが減る(): void
    {
        $this->useItem(self::STAMINA_ITEM_ID, 3)->assertOk();

        $this->assertSame(7, (int) DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', self::STAMINA_ITEM_ID)
            ->value('free_amount'));
    }

    #[Test]
    public function 持っている数より多くは使えない(): void
    {
        $response = $this->useItem(self::STAMINA_ITEM_ID, 11);

        // 業務エラーは HTTP 299 で返し、アイテムは減らない
        $response->assertStatus(299);
        $response->assertJsonStructure(['error_code', 'message']);
        $this->assertSame(10, (int) DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', self::STAMINA_ITEM_ID)
            ->value('free_amount'));
    }

    #[Test]
    public function 無償ダイヤはアイテムとして使えず所持数は変わらない(): void
    {
        // サインアップの初期付与で free_diamond は 50 ある。effect が none のマスターを置く
        DB::connection('mst')->table('mst_item')->insertOrIgnore(
            ['id' => 'free_diamond', 'type' => 'currency', 'effect' => 'none', 'value' => 0]
        );
        $this->refreshMstCache();

        $response = $this->useItem('free_diamond', 1);

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::INVALID_ITEM_TYPE, $response->json('error_code'));
        $this->assertSame(50, (int) DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', 'free_diamond')->value('free_amount'));
    }

    #[Test]
    public function スタミナ回復薬50を使うとスタミナが50回復する(): void
    {
        // ログインボーナス 5 日目の景品 stamina_50 は、シーダーの行（type=stamina・effect=stamina_recover・
        // value=50）のまま /item/use で使える。新しいコードは要らず、既存の STAMINA_RECOVER の経路を通る。
        // シーダーの行を使うので、マスターが変わったときにこのテストが気づく。
        // シーダーは mst_item を truncate するので、後始末は tearDown（$seededItems）で消す
        (new MstItemSeeder)->run();
        $this->seededItems = true;
        $this->refreshMstCache();

        DB::connection($this->connection)->table('trx_item')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'mst_item_id' => 'stamina_50',
            'free_amount' => 3,
            'paid_amount' => 0,
            'is_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->useItem('stamina_50', 1);

        $response->assertOk();
        $response->assertJson([
            'mst_item_id' => 'stamina_50',
            'effect' => 'stamina_recover',
            'item_used' => 1,
            'applied_value' => 50,
        ]);

        // スタミナは 10 → 60（最大値を超えて回復できる。既存の仕様）
        $this->assertSame(60, (int) DB::connection($this->connection)->table('trx_stamina')
            ->where('sys_player_id', $this->sysPlayerId)->where('type', StaminaConst::TYPE_NORMAL)->value('current_stamina'));

        // 薬は 3 → 2
        $this->assertSame(2, (int) DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)->where('mst_item_id', 'stamina_50')->value('free_amount'));
        $this->assertArrayHasKey('trx_stamina_list', $response->json('updated_resources'));
    }

    #[Test]
    public function 使う数が無いとバリデーションエラーになる(): void
    {
        $this->postJson('/api/item/use', ['mst_item_id' => self::STAMINA_ITEM_ID], $this->authHeaders($this->accessToken))
            ->assertStatus(422);
    }

    #[Test]
    public function 認証が無いと使えない(): void
    {
        $this->postJson('/api/item/use', ['mst_item_id' => self::STAMINA_ITEM_ID, 'use_count' => 1])
            ->assertStatus(401);
    }

    private function useItem(string $mstItemId, int $useCount): TestResponse
    {
        return $this->postJson(
            '/api/item/use',
            ['mst_item_id' => $mstItemId, 'use_count' => $useCount],
            $this->authHeaders($this->accessToken),
        );
    }

    private function createMaster(): void
    {
        DB::connection('mst')->table('mst_player_level')->insert([
            ['level' => 1, 'required_exp' => 0, 'max_stamina' => 50, 'created_at' => now(), 'updated_at' => now()],
            ['level' => 2, 'required_exp' => 100, 'max_stamina' => 55, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection('mst')->table('mst_item')->insert([
            'id' => self::STAMINA_ITEM_ID,
            'type' => 'consumable',
            'effect' => 'stamina_recover',
            'value' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function cleanUpMaster(): void
    {
        DB::connection('mst')->table('mst_player_level')->whereIn('level', [1, 2])->delete();
        DB::connection('mst')->table('mst_item')->where('id', self::STAMINA_ITEM_ID)->delete();
    }

    private function createPlayerData(): void
    {
        // サインアップ時に初期スタミナの行（type = normal）ができているので、テスト用の値に置き換える
        DB::connection($this->connection)->table('trx_stamina')->updateOrInsert(
            ['sys_player_id' => $this->sysPlayerId, 'type' => StaminaConst::TYPE_NORMAL],
            [
                'current_stamina' => 10,
                'recovery_rate_multiplier' => 1.00,
                'last_recovery_at' => now()->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::connection($this->connection)->table('trx_item')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'mst_item_id' => self::STAMINA_ITEM_ID,
            'free_amount' => 10,
            'paid_amount' => 0,
            'is_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
