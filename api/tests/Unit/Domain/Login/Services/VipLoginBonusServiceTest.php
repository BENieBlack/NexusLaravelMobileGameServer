<?php

namespace Tests\Unit\Domain\Login\Services;

use App\Domain\Login\Services\VipLoginBonusService;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use App\Models\Mst\MstVipLoginBonus;
use App\Models\Mst\MstVipLoginBonusContent;
use App\Models\Sys\SysPlayer;
use App\Persistence\ApiSession;
use Carbon\CarbonImmutable;
use NexusUtilities\ClockUtility;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * VipLoginBonusServiceのテスト
 * 
 * VIPレベル別ログインボーナスの配布ロジックをテスト
 */
class VipLoginBonusServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId = 1;
    private VipLoginBonusService $vipLoginBonusService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // テストプレイヤーとシャーディング情報を作成
        $this->createTestPlayer();
        
        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);
        
        $this->vipLoginBonusService = app(VipLoginBonusService::class);
        
        // テスト用のVIPログインボーナスマスターを作成
        $this->createVipLoginBonusMasterData();
    }

    /**
     * テストプレイヤーとシャーディング情報を作成
     */
    private function createTestPlayer(int $vipLevel = 0, int $vipPoint = 0): void
    {
        // sys_shardingを作成（存在しなければ）
        $shardingId = DB::connection('sys')->table('sys_sharding')
            ->where('name', 'trx_sharding')
            ->value('id');

        if (!$shardingId) {
            $shardingId = DB::connection('sys')->table('sys_sharding')->insertGetId([
                'name' => 'trx_sharding',
                'target' => 'transaction',
                'strategy' => 'hash',
                'sharding_key' => 'player_id',
                'node_count' => 2,
                'is_active' => true,
                'description' => 'Test sharding',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // sys_sharding_nodeを作成（存在しなければ）
        $nodeId = DB::connection('sys')->table('sys_sharding_node')
            ->where('sys_sharding_id', $shardingId)
            ->where('node_name', 'node1')
            ->value('id');

        if (!$nodeId) {
            $nodeId = DB::connection('sys')->table('sys_sharding_node')->insertGetId([
                'sys_sharding_id' => $shardingId,
                'node_name' => 'node1',
                'node_no' => 1,
                'weight' => 100,
                'status' => 'active',
                'is_writable' => true,
                'is_readable' => true,
                'max_connections' => 10000,
                'current_player_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // sys_playerを作成（VIPレベル・VIPポイント付き）
        DB::connection('sys')->table('sys_player')->insert([
            'id' => $this->sysPlayerId,
            'uuid' => 'test-uuid-' . uniqid(),
            'my_id' => 'TEST0001',
            'name' => 'Test Player',
            'vip_level' => $vipLevel,
            'vip_point' => $vipPoint,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // シャーディング情報を作成
        DB::connection('sys')->table('sys_sharding_node_player')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'sys_sharding_node_id' => $nodeId,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        // テストデータをクリア
        DB::connection('trx1')->table('trx_vip_login_bonus_history')->truncate();
        DB::connection('mst')->table('mst_vip_login_bonus_content')->delete();
        DB::connection('mst')->table('mst_vip_login_bonus')->delete();
        DB::connection('sys')->table('sys_sharding_node_player')->where('sys_player_id', $this->sysPlayerId)->delete();
        DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->delete();
        
        ApiSession::clearForTest();
        parent::tearDown();
    }

    /**
     * テスト用のVIPログインボーナスマスターデータを作成
     * VIP0, VIP5, VIP10の3レベルのみ作成（テスト簡略化）
     */
    private function createVipLoginBonusMasterData(): void
    {
        foreach ([0, 5, 10] as $vipLevel) {
            $bonusId = "vip_login_lv{$vipLevel}";
            
            MstVipLoginBonus::create([
                'id' => $bonusId,
                'vip_level' => $vipLevel,
                'loop_days' => 7,
                'is_active' => true,
            ]);

            // 簡略化: 各レベルで1〜7日目にゴールドのみ配布
            $multiplier = 1 + ($vipLevel * 0.3);
            for ($day = 1; $day <= 7; $day++) {
                $goldAmount = (int) floor(1000 * $day * $multiplier);
                
                MstVipLoginBonusContent::create([
                    'mst_vip_login_bonus_id' => $bonusId,
                    'day' => $day,
                    'content_type' => 'currency',
                    'content_id' => 'gold',
                    'content_option' => null,
                    'content_quantity' => $goldAmount,
                    'amount' => 1,
                ]);
            }
        }
    }

    #[Test]
    public function VIP0プレイヤーがログインボーナスを受け取れる(): void
    {
        // Arrange
        $lastLoginAt = null;

        // Act
        $isEligible = $this->vipLoginBonusService->isEligible($this->sysPlayerId, $lastLoginAt);

        // Assert
        $this->assertTrue($isEligible, 'VIP0プレイヤーは初回ログインでボーナスを受け取れるべき');
    }

    #[Test]
    public function VIP5プレイヤーがVIP5のボーナスを受け取る(): void
    {
        // Arrange: VIP5のプレイヤーを作成
        DB::connection('sys')->table('sys_player')
            ->where('id', $this->sysPlayerId)
            ->update(['vip_level' => 5, 'vip_point' => 5000]);

        $lastLoginAt = null;

        // Act
        $result = $this->vipLoginBonusService->grant($this->sysPlayerId, $lastLoginAt);

        // Assert
        $this->assertNotNull($result, 'VIP5プレイヤーはボーナスを受け取れるべき');
        $this->assertEquals(1, $result['current_day'], '初回は1日目のボーナスを受け取るべき');
        
        // VIP5の1日目のゴールド量を確認（1000 * 1 * 2.5 = 2500）
        $this->assertGreaterThan(1000, $result['contents'][0]->content_quantity, 'VIP5はVIP0より多くのゴールドを受け取るべき');
    }

    #[Test]
    public function 同じ日に2回受け取れない(): void
    {
        // Arrange: 既に今日受け取っている状態を作る
        $currentTime = ClockUtility::now();
        $lastLoginAt = $currentTime->format('Y-m-d H:i:s');

        // 1回目の受け取り
        $this->vipLoginBonusService->grant($this->sysPlayerId, null);

        // Act: 同じ日に再度受け取ろうとする
        $isEligible = $this->vipLoginBonusService->isEligible($this->sysPlayerId, $lastLoginAt);

        // Assert
        $this->assertFalse($isEligible, '同じ日に2回受け取れないべき');
    }

    #[Test]
    public function ループ処理が正しく動作する(): void
    {
        // Arrange: 7日分の履歴を作成
        $connectionName = 'trx1';
        $bonusId = 'vip_login_lv0';
        
        for ($day = 1; $day <= 7; $day++) {
            DB::connection($connectionName)->table('trx_vip_login_bonus_history')->insert([
                'sys_player_id' => $this->sysPlayerId,
                'mst_vip_login_bonus_id' => $bonusId,
                'day' => $day,
                'vip_level' => 0,
                'received_at' => now()->subDays(7 - $day)->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Act: 8日目にログイン（1日目に戻るべき）
        $result = $this->vipLoginBonusService->grant($this->sysPlayerId, now()->subDay()->format('Y-m-d H:i:s'));

        // Assert
        $this->assertNotNull($result, '8日目にボーナスを受け取れるべき');
        $this->assertEquals(1, $result['current_day'], '8日目は1日目にループするべき');
    }

    #[Test]
    public function VIPレベルがない場合はボーナスを受け取れない(): void
    {
        // Arrange: VIP3のプレイヤー（VIP3のマスターデータは作成していない）
        DB::connection('sys')->table('sys_player')
            ->where('id', $this->sysPlayerId)
            ->update(['vip_level' => 3, 'vip_point' => 3000]);

        $lastLoginAt = null;

        // Act
        $isEligible = $this->vipLoginBonusService->isEligible($this->sysPlayerId, $lastLoginAt);

        // Assert
        $this->assertFalse($isEligible, 'VIPレベルに対応するマスターデータがない場合は受け取れないべき');
    }

    #[Test]
    public function VIPレベルアップ後は新しいVIPレベルのボーナスを受け取る(): void
    {
        // Arrange: VIP0で3日目まで受け取った後、VIP5にレベルアップ
        $connectionName = 'trx1';
        $bonusIdVip0 = 'vip_login_lv0';
        
        // VIP0で3日分受け取り
        for ($day = 1; $day <= 3; $day++) {
            DB::connection($connectionName)->table('trx_vip_login_bonus_history')->insert([
                'sys_player_id' => $this->sysPlayerId,
                'mst_vip_login_bonus_id' => $bonusIdVip0,
                'day' => $day,
                'vip_level' => 0,
                'received_at' => now()->subDays(3 - $day)->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // VIP5にレベルアップ
        DB::connection('sys')->table('sys_player')
            ->where('id', $this->sysPlayerId)
            ->update(['vip_level' => 5, 'vip_point' => 5000]);

        // Act: 4日目にログイン
        $result = $this->vipLoginBonusService->grant($this->sysPlayerId, now()->subDay()->format('Y-m-d H:i:s'));

        // Assert
        $this->assertNotNull($result, 'VIPレベルアップ後もボーナスを受け取れるべき');
        $this->assertEquals(4, $result['current_day'], '4日目のボーナスを受け取るべき');
        
        // VIP5の4日目のゴールド量を確認（1000 * 4 * 2.5 = 10000）
        $this->assertEquals(10000, $result['contents'][0]->content_quantity, 'VIP5の報酬量になるべき');
        
        // 履歴を確認
        $history = DB::connection($connectionName)->table('trx_vip_login_bonus_history')
            ->where('sys_player_id', $this->sysPlayerId)
            ->orderBy('id', 'desc')
            ->first();
        
        $this->assertEquals(5, $history->vip_level, '履歴にVIP5として記録されるべき');
        $this->assertEquals('vip_login_lv5', $history->mst_vip_login_bonus_id, 'VIP5のボーナスIDが記録されるべき');
    }

    #[Test]
    public function 日跨ぎ判定が正しく動作する(): void
    {
        // Arrange: 昨日最後にログインした
        $yesterday = ClockUtility::now()->subDay();
        $lastLoginAt = $yesterday->format('Y-m-d H:i:s');

        // Act
        $isEligible = $this->vipLoginBonusService->isEligible($this->sysPlayerId, $lastLoginAt);

        // Assert
        $this->assertTrue($isEligible, '日跨ぎ後は受け取れるべき');
    }

    #[Test]
    public function 無効なボーナスは受け取れない(): void
    {
        // Arrange: VIP0のボーナスを無効化
        DB::connection('mst')->table('mst_vip_login_bonus')
            ->where('id', 'vip_login_lv0')
            ->update(['is_active' => false]);

        $lastLoginAt = null;

        // Act
        $isEligible = $this->vipLoginBonusService->isEligible($this->sysPlayerId, $lastLoginAt);

        // Assert
        $this->assertFalse($isEligible, '無効なボーナスは受け取れないべき');
    }
}
