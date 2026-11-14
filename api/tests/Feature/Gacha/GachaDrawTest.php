<?php

namespace Tests\Feature\Gacha;

use App\Exceptions\GameErrorCode;
use App\Models\Mst\MstGacha;
use App\Models\Mst\MstGachaCost;
use App\Models\Mst\MstGachaPrize;
use App\Models\Mst\MstGachaRarityRate;
use App\Models\Mst\MstGachaStep;
use App\Models\Mst\MstItem;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Models\Trx\TrxEquipment;
use App\Models\Trx\TrxItem;
use App\Models\Trx\TrxUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Nexus\Core\Utilities\ClockUtility;
use NexusAuth\Services\TokenService;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * GachaDrawTest
 *
 * ガチャ実行API (/api/gacha/draw) の統合テスト
 * 全てのコンテンツタイプ (item, unit, equipment) の排出をテスト
 */
class GachaDrawTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $testPlayerId;

    private string $testAccessToken;

    private string $testGachaId = 'test_gacha_all_contents';

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::initialize();

        // テストプレイヤーとトークンを作成
        $this->createTestPlayer();

        // ガチャマスターデータを作成
        $this->createGachaMasterData();

        // Mstリポジトリのキャッシュをクリアして、新しく作成したデータを読み込ませる
        $this->refreshMstCache();
    }

    /**
     * テストプレイヤーとアクセストークンを作成
     */
    private function createTestPlayer(): void
    {
        // プレイヤー作成
        $player = SysPlayer::create([
            'uuid' => 'test-player-uuid-gacha',
            'my_id' => 'TEST1234',
            'name' => 'Test Player',
        ]);
        $this->testPlayerId = $player->id;

        // デバイス作成
        $device = SysPlayerDevice::create([
            'sys_player_id' => $player->id,
            'uuid' => 'test-device-uuid-gacha',
            'device_info' => ['os' => 'iOS', 'model' => 'iPhone 15'],
        ]);

        // トークン作成（JWTを生成）
        $tokenService = app(TokenService::class);
        $accessToken = $tokenService->generateAccessToken($player, $device);
        $this->testAccessToken = $accessToken;

        // リフレッシュトークンも保存
        SysPlayerToken::create([
            'sys_player_id' => $player->id,
            'sys_player_device_id' => $device->id,
            'refresh_token_hash' => hash('sha256', 'test-refresh-token-gacha'),
            'expires_at' => ClockUtility::now()->addDays(30),
        ]);

        // ApiSessionにプレイヤーIDを設定
        $this->useSessionPlayer($this->testPlayerId);

        // 初期の無償ダイヤを付与（ガチャコスト用）。無償ダイヤは trx_item の free_diamond
        $this->seedFreeDiamond($this->testPlayerId, 1000);
    }

    /**
     * ガチャマスターデータを作成
     */
    private function createGachaMasterData(): void
    {
        // ガチャマスター
        MstGacha::create([
            'id' => $this->testGachaId,
            'deploy_key' => 202601010,
            'sort_desc' => 100,
            'is_active' => true,
            'start_at' => ClockUtility::now()->subDay(),
            'end_at' => ClockUtility::now()->addDay(),
            'daily_limit' => 0, // 無制限
            'has_step_up' => false,
        ]);

        // ガチャコスト（ダイヤモンド100個）
        MstGachaCost::create([
            'id' => $this->testGachaId.'_cost_1',
            'deploy_key' => 202601010,
            'mst_gacha_id' => $this->testGachaId,
            'draw_count' => 1,
            'cost_type' => 'diamond',
            'cost_mst_id' => 'diamond',
            'cost_amount' => 100,
        ]);

        // ガチャコスト（10連: ダイヤモンド900個）
        MstGachaCost::create([
            'id' => $this->testGachaId.'_cost_10',
            'deploy_key' => 202601010,
            'mst_gacha_id' => $this->testGachaId,
            'draw_count' => 10,
            'cost_type' => 'diamond',
            'cost_mst_id' => 'diamond',
            'cost_amount' => 900,
        ]);

        // ガチャステップ（通常ガチャ）
        MstGachaStep::create([
            'deploy_key' => 202601010,
            'id' => $this->testGachaId.'_step_1',
            'mst_gacha_id' => $this->testGachaId,
            'step_number' => 1,
            'draw_count' => 1,
            'is_loop_start' => true,
            'is_active' => true,
        ]);

        // レアリティ排出率（rarity 3: 70%, rarity 4: 30%）
        MstGachaRarityRate::create([
            'id' => $this->testGachaId.'_rate_r3',
            'deploy_key' => 202601010,
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 3,
            'rate' => 7000, // 70%
        ]);

        MstGachaRarityRate::create([
            'id' => $this->testGachaId.'_rate_r4',
            'deploy_key' => 202601010,
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 4,
            'rate' => 3000, // 30%
        ]);

        // ガチャプール（各コンテンツタイプ）
        $this->createGachaPools();

        // マスターアイテムを作成
        $this->createMasterItems();
    }

    /**
     * ガチャプールを作成（全コンテンツタイプ）
     */
    private function createGachaPools(): void
    {
        // Item (rarity 3)
        MstGachaPrize::create([
            'deploy_key' => 202601010,
            'id' => $this->testGachaId.'_prize_item_r3',
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 3,
            'content_type' => 'item',
            'content_mst_id' => 'test_item_gold',
            'amount' => 100,
            'weight' => 100,
            'is_active' => true,
        ]);

        // Item (rarity 4) - 追加
        MstGachaPrize::create([
            'deploy_key' => 202601010,
            'id' => $this->testGachaId.'_prize_item_r4',
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 4,
            'content_type' => 'item',
            'content_mst_id' => 'test_item_diamond',
            'amount' => 50,
            'weight' => 100,
            'is_active' => true,
        ]);

        // Unit (rarity 3) - 追加
        MstGachaPrize::create([
            'deploy_key' => 202601010,
            'id' => $this->testGachaId.'_prize_unit_r3',
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 3,
            'content_type' => 'unit',
            'content_mst_id' => 'test_unit_soldier',
            'amount' => 1,
            'weight' => 100,
            'is_active' => true,
        ]);

        // Unit (rarity 4)
        MstGachaPrize::create([
            'deploy_key' => 202601010,
            'id' => $this->testGachaId.'_prize_unit_r4',
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 4,
            'content_type' => 'unit',
            'content_mst_id' => 'test_unit_hero',
            'amount' => 1,
            'weight' => 100,
            'is_active' => true,
        ]);

        // Equipment (rarity 3) - 追加
        MstGachaPrize::create([
            'deploy_key' => 202601010,
            'id' => $this->testGachaId.'_prize_equipment_r3',
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 3,
            'content_type' => 'equipment',
            'content_mst_id' => 'test_equipment_dagger',
            'amount' => 1,
            'weight' => 100,
            'is_active' => true,
        ]);

        // Equipment (rarity 4)
        MstGachaPrize::create([
            'deploy_key' => 202601010,
            'id' => $this->testGachaId.'_prize_equipment_r4',
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 4,
            'content_type' => 'equipment',
            'content_mst_id' => 'test_equipment_sword',
            'amount' => 1,
            'weight' => 100,
            'is_active' => true,
        ]);
    }

    /**
     * マスターアイテムを作成
     */
    private function createMasterItems(): void
    {
        MstItem::create([
            'id' => 'test_item_gold',
            'deploy_key' => 202601010,
            'type' => 'material',
            'effect' => 'none',
            'value' => 0,
        ]);

        MstItem::create([
            'id' => 'test_item_diamond',
            'deploy_key' => 202601010,
            'type' => 'material',
            'effect' => 'none',
            'value' => 0,
        ]);
    }

    /**
     * Test: ガチャ実行で全てのコンテンツタイプが排出可能（統合テスト）
     *
     * 1連ガチャを引いて、item/unit/equipmentのいずれかが排出されることを確認
     */
    public function test_gacha_draw_works_with_all_content_types(): void
    {
        // Arrange
        $requestData = [
            'mst_gacha_id' => $this->testGachaId,
            'draw_count' => 1, // 1連ガチャ
        ];

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->testAccessToken,
        ])->postJson('/api/gacha/draw', $requestData);

        // Assert
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('prize_list', $data);
        $this->assertCount(1, $data['prize_list']);

        // prizeが有効なcontent_typeを持っていることを確認
        $this->assertArrayHasKey('content_type', $data['prize_list'][0]);
        $this->assertContains($data['prize_list'][0]['content_type'], ['item', 'unit', 'equipment']);

        // データベース確認：何かしらのコンテンツが配布されている
        // 無償ダイヤの行（free_diamond）は景品ではないので数えない
        $itemCount = TrxItem::where('sys_player_id', $this->testPlayerId)->where('mst_item_id', '!=', 'free_diamond')->count();
        $unitCount = TrxUnit::where('sys_player_id', $this->testPlayerId)->count();
        $equipmentCount = TrxEquipment::where('sys_player_id', $this->testPlayerId)->count();

        $totalCount = $itemCount + $unitCount + $equipmentCount;
        $this->assertEquals(1, $totalCount, 'Should have 1 item distributed');
    }

    /**
     * Test: ダイヤが足りなければ消費する前に業務エラー（299）を返し、何も変えない
     */
    public function test_gacha_draw_without_enough_diamond_returns_business_error(): void
    {
        $this->seedFreeDiamond($this->testPlayerId, 50);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->testAccessToken,
        ])->postJson('/api/gacha/draw', [
            'mst_gacha_id' => $this->testGachaId,
            'draw_count' => 1, // 100ダイヤ必要
        ]);

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::DIAMOND_NOT_ENOUGH, $response->json('error_code'));

        $this->assertSame(50, $this->freeDiamondAmount());
        $this->assertSame(0, TrxItem::where('sys_player_id', $this->testPlayerId)->where('mst_item_id', '!=', 'free_diamond')->count()
            + TrxUnit::where('sys_player_id', $this->testPlayerId)->count()
            + TrxEquipment::where('sys_player_id', $this->testPlayerId)->count());
    }

    /**
     * Test: 無償だけで足りるとき、無償だけを減らし、updated_resources には trx_item_list だけが載る
     */
    public function test_gacha_draw_consumes_free_diamond_only_and_returns_only_trx_item_list(): void
    {
        $this->seedPaidDiamond($this->testPlayerId, 'Apple', 500);

        $response = $this->drawOnce();

        $response->assertStatus(200);
        $this->assertSame(900, $this->freeDiamondAmount());
        $this->assertSame(500, $this->paidAmount('Apple'));
        $this->assertSame(500, $this->balanceTotal('Apple'));
        // 変更のあった方だけ載る。有償は変わっていないので trx_diamond は省略
        $this->assertArrayNotHasKey('trx_diamond', $response->json('updated_resources'));
        $freeDiamond = collect($response->json('updated_resources.trx_item_list'))->firstWhere('mst_item_id', 'free_diamond');
        $this->assertSame(900, $freeDiamond['free_amount']);
    }

    /**
     * Test: 無償を使い切って有償にまたがる。無償の行は 0 で残り、paid_amount と balance が連動して減る
     */
    public function test_gacha_draw_consumes_free_then_paid_and_returns_both(): void
    {
        $this->seedFreeDiamond($this->testPlayerId, 30);
        $this->seedPaidDiamond($this->testPlayerId, 'Apple', 500);

        $response = $this->drawOnce();

        $response->assertStatus(200);
        // 100 = 無償 30 + 有償 70
        $this->assertSame(0, $this->freeDiamondAmount());
        $this->assertSame(1, DB::connection($this->playerConnection($this->testPlayerId))->table('trx_item')
            ->where('sys_player_id', $this->testPlayerId)->where('mst_item_id', 'free_diamond')->count());
        $this->assertSame(430, $this->paidAmount('Apple'));
        $this->assertSame(430, $this->balanceTotal('Apple'));

        // 両方載る。trx_diamond は単数のオブジェクトで、free_amount は無い
        $freeDiamond = collect($response->json('updated_resources.trx_item_list'))->firstWhere('mst_item_id', 'free_diamond');
        $this->assertSame(0, $freeDiamond['free_amount']);
        $this->assertSame('Apple', $response->json('updated_resources.trx_diamond.platform'));
        $this->assertSame(430, $response->json('updated_resources.trx_diamond.paid_amount'));
        $this->assertArrayNotHasKey('free_amount', $response->json('updated_resources.trx_diamond'));
        $this->assertArrayNotHasKey('trx_diamond_list', $response->json('updated_resources'));
    }

    /**
     * Test: コスト paid_diamond は有償だけを減らす（無償は触らない）
     */
    public function test_gacha_draw_with_paid_diamond_cost_consumes_paid_only(): void
    {
        $this->changeSingleDrawCost('paid_diamond', 'paid_diamond');
        $this->seedPaidDiamond($this->testPlayerId, 'Apple', 500);

        $response = $this->drawOnce();

        $response->assertStatus(200);
        $this->assertSame(1000, $this->freeDiamondAmount());
        $this->assertSame(400, $this->paidAmount('Apple'));
        $this->assertSame(400, $this->balanceTotal('Apple'));
        $freeDiamondRows = collect($response->json('updated_resources.trx_item_list') ?? [])->where('mst_item_id', 'free_diamond');
        $this->assertCount(0, $freeDiamondRows);
        $this->assertSame(400, $response->json('updated_resources.trx_diamond.paid_amount'));
    }

    /**
     * Test: 他 platform（Google）の有償は、X-Platform（Apple）のガチャのコストに使えない
     */
    public function test_gacha_draw_does_not_use_paid_diamond_of_other_platform(): void
    {
        $this->seedFreeDiamond($this->testPlayerId, 50);
        $this->seedPaidDiamond($this->testPlayerId, 'Google', 500);

        $response = $this->drawOnce();

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::DIAMOND_NOT_ENOUGH, $response->json('error_code'));
        $this->assertSame(50, $this->freeDiamondAmount());
        $this->assertSame(500, $this->paidAmount('Google'));
        $this->assertSame(500, $this->balanceTotal('Google'));
    }

    /**
     * Test: balance が paid_amount に満たないデータ不整合は、無償・有償・balance のすべてを巻き戻す
     */
    public function test_gacha_draw_rolls_back_everything_when_balance_is_less_than_paid_amount(): void
    {
        $connection = DB::connection($this->playerConnection($this->testPlayerId));
        $this->seedFreeDiamond($this->testPlayerId, 30);
        $this->seedPaidDiamond($this->testPlayerId, 'Apple', 500);
        // 不整合を作る: balance だけを 30 に減らす（有償 70 を引ききれない）
        $connection->table('trx_diamond_balance')->where('sys_player_id', $this->testPlayerId)->update(['current_amount' => 30]);

        $response = $this->drawOnce();

        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(GameErrorCode::DIAMOND_NOT_ENOUGH, $response->json('error_code'));
        $this->assertSame(30, $this->freeDiamondAmount());
        $this->assertSame(500, $this->paidAmount('Apple'));
        $this->assertSame(30, $this->balanceTotal('Apple'));
    }

    /**
     * Test: コスト cost_type = item で free_diamond を指すマスターは、所持していても拒否され何も変わらない
     */
    public function test_gacha_draw_rejects_free_diamond_as_item_cost(): void
    {
        $this->changeSingleDrawCost('item', 'free_diamond');

        $response = $this->drawOnce();

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::INVALID_ITEM_TYPE, $response->json('error_code'));
        $this->assertSame(1000, $this->freeDiamondAmount());
    }

    private function drawOnce(): TestResponse
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->testAccessToken])
            ->postJson('/api/gacha/draw', [
                'mst_gacha_id' => $this->testGachaId,
                'draw_count' => 1, // コスト 100
            ]);
    }

    /**
     * 単発のコスト（100）の種類を差し替える
     */
    private function changeSingleDrawCost(string $costType, string $costMstId): void
    {
        DB::connection('mst')->table('mst_gacha_cost')
            ->where('id', $this->testGachaId.'_cost_1')
            ->update(['cost_type' => $costType, 'cost_mst_id' => $costMstId]);
        $this->refreshMstCache();
    }

    private function freeDiamondAmount(): int
    {
        return (int) DB::connection($this->playerConnection($this->testPlayerId))->table('trx_item')
            ->where('sys_player_id', $this->testPlayerId)->where('mst_item_id', 'free_diamond')->value('free_amount');
    }

    private function paidAmount(string $platform): int
    {
        return (int) DB::connection($this->playerConnection($this->testPlayerId))->table('trx_diamond')
            ->where('sys_player_id', $this->testPlayerId)->where('platform', $platform)->value('paid_amount');
    }

    /**
     * その platform の trx_diamond_balance の current_amount の合計
     */
    private function balanceTotal(string $platform): int
    {
        return (int) DB::connection($this->playerConnection($this->testPlayerId))->table('trx_diamond_balance')
            ->where('sys_player_id', $this->testPlayerId)->where('platform', $platform)->sum('current_amount');
    }
}
