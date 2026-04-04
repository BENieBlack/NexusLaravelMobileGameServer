<?php

namespace Tests\Feature\Gacha;

use App\Domain\Auth\Services\TokenService;
use App\Models\Mst\MstGacha;
use App\Models\Mst\MstGachaCost;
use App\Models\Mst\MstGachaPrize;
use App\Models\Mst\MstGachaRarityRate;
use App\Models\Mst\MstGachaStep;
use App\Models\Mst\MstItem;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use App\Models\Trx\TrxItem;
use App\Models\Trx\TrxUnit;
use App\Models\Trx\TrxEquipment;
use App\Models\Trx\TrxDiamond;
use App\Persistence\ApiSession;
use App\Utilities\Clock;
use Carbon\Carbon;
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
        
        Clock::initialize();
        
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
            'expires_at' => Carbon::now()->addDays(30),
        ]);
        
        // ApiSessionにプレイヤーIDを設定
        ApiSession::setSysPlayerId($this->testPlayerId);
        
        // 初期ダイヤモンドを付与（ガチャコスト用）
        TrxDiamond::create([
            'sys_player_id' => $this->testPlayerId,
            'platform' => 'ios',
            'free_amount' => 1000,
            'paid_amount' => 0,
        ]);
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
            'start_at' => Carbon::now()->subDay(),
            'end_at' => Carbon::now()->addDay(),
            'daily_limit' => 0, // 無制限
            'has_step_up' => false,
        ]);

        // ガチャコスト（ダイヤモンド100個）
        MstGachaCost::create([
            'id' => $this->testGachaId . '_cost_1',
            'deploy_key' => 202601010,
            'mst_gacha_id' => $this->testGachaId,
            'draw_count' => 1,
            'cost_type' => 'diamond',
            'cost_id' => 'diamond',
            'cost_amount' => 100,
        ]);

        // ガチャコスト（10連: ダイヤモンド900個）
        MstGachaCost::create([
            'id' => $this->testGachaId . '_cost_10',
            'deploy_key' => 202601010,
            'mst_gacha_id' => $this->testGachaId,
            'draw_count' => 10,
            'cost_type' => 'diamond',
            'cost_id' => 'diamond',
            'cost_amount' => 900,
        ]);

        // ガチャステップ（通常ガチャ）
        MstGachaStep::create([
            'deploy_key' => 202601010,
            'id' => $this->testGachaId . '_step_1',
            'mst_gacha_id' => $this->testGachaId,
            'step_number' => 1,
            'draw_count' => 1,
            'is_loop_start' => true,
            'is_active' => true,
        ]);

        // レアリティ排出率（rarity 3: 70%, rarity 4: 30%）
        MstGachaRarityRate::create([
            'id' => $this->testGachaId . '_rate_r3',
            'deploy_key' => 202601010,
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 3,
            'rate' => 7000, // 70%
        ]);
        
        MstGachaRarityRate::create([
            'id' => $this->testGachaId . '_rate_r4',
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
            'id' => $this->testGachaId . '_prize_item_r3',
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 3,
            'content_type' => 'item',
            'content_id' => 'test_item_gold',
            'amount' => 100,
            'weight' => 100,
            'is_active' => true,
        ]);

        // Item (rarity 4) - 追加
        MstGachaPrize::create([
            'deploy_key' => 202601010,
            'id' => $this->testGachaId . '_prize_item_r4',
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 4,
            'content_type' => 'item',
            'content_id' => 'test_item_diamond',
            'amount' => 50,
            'weight' => 100,
            'is_active' => true,
        ]);

        // Unit (rarity 3) - 追加
        MstGachaPrize::create([
            'deploy_key' => 202601010,
            'id' => $this->testGachaId . '_prize_unit_r3',
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 3,
            'content_type' => 'unit',
            'content_id' => 'test_unit_soldier',
            'amount' => 1,
            'weight' => 100,
            'is_active' => true,
        ]);

        // Unit (rarity 4)
        MstGachaPrize::create([
            'deploy_key' => 202601010,
            'id' => $this->testGachaId . '_prize_unit_r4',
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 4,
            'content_type' => 'unit',
            'content_id' => 'test_unit_hero',
            'amount' => 1,
            'weight' => 100,
            'is_active' => true,
        ]);

        // Equipment (rarity 3) - 追加
        MstGachaPrize::create([
            'deploy_key' => 202601010,
            'id' => $this->testGachaId . '_prize_equipment_r3',
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 3,
            'content_type' => 'equipment',
            'content_id' => 'test_equipment_dagger',
            'amount' => 1,
            'weight' => 100,
            'is_active' => true,
        ]);

        // Equipment (rarity 4)
        MstGachaPrize::create([
            'deploy_key' => 202601010,
            'id' => $this->testGachaId . '_prize_equipment_r4',
            'mst_gacha_id' => $this->testGachaId,
            'rarity' => 4,
            'content_type' => 'equipment',
            'content_id' => 'test_equipment_sword',
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
            'Authorization' => 'Bearer ' . $this->testAccessToken,
        ])->postJson('/api/gacha/draw', $requestData);

        // Assert
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertArrayHasKey('prizes', $data);
        $this->assertCount(1, $data['prizes']);

        // prizeが有効なcontent_typeを持っていることを確認
        $this->assertArrayHasKey('content_type', $data['prizes'][0]);
        $this->assertContains($data['prizes'][0]['content_type'], ['item', 'unit', 'equipment']);

        // データベース確認：何かしらのコンテンツが配布されている
        $itemCount = TrxItem::where('sys_player_id', $this->testPlayerId)->count();
        $unitCount = TrxUnit::where('sys_player_id', $this->testPlayerId)->count();
        $equipmentCount = TrxEquipment::where('sys_player_id', $this->testPlayerId)->count();
        
        $totalCount = $itemCount + $unitCount + $equipmentCount;
        $this->assertEquals(1, $totalCount, 'Should have 1 item distributed');
    }
}
