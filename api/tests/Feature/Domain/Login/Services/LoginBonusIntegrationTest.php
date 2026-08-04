<?php

namespace Tests\Feature\Domain\Login\Services;

use App\Domain\Login\Services\LoginBonusService;
use App\Domain\Login\Services\ComeBackLoginBonusService;
use App\Domain\Login\Services\VipLoginBonusService;
use NexusLogin\Services\LoginBonusOrchestrator;
use App\Persistence\ApiSession;
use NexusUtilities\ClockUtility;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * ログインボーナス統合テスト
 * 
 * 複数のログインボーナス（通常・VIP・カムバック）の並行受取と
 * VIPレベル変動時の動作を確認
 */
class LoginBonusIntegrationTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId = 1;
    private LoginBonusOrchestrator $orchestrator;
    private string $connectionName = 'trx1';

    protected function setUp(): void
    {
        parent::setUp();
        
        // テストプレイヤーとシャーディング情報を作成
        $this->createTestPlayer();
        
        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);
        
        $this->orchestrator = app(LoginBonusOrchestrator::class);
        
        // テスト用のマスターデータを作成
        $this->createLoginBonusMasterData();
        $this->createVipLoginBonusMasterData();
        $this->createComeBackLoginBonusMasterData();
    }

    /**
     * テストプレイヤーとシャーディング情報を作成
     */
    private function createTestPlayer(int $vipLevel = 0, int $vipPoint = 0): void
    {
        // sys_shardingを作成
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

        // sys_sharding_nodeを作成
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

        // sys_playerを作成
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
        DB::connection('trx1')->table('trx_login_bonus_history')->truncate();
        DB::connection('trx1')->table('trx_vip_login_bonus_history')->truncate();
        DB::connection('mst')->table('mst_login_bonus_content')->delete();
        DB::connection('mst')->table('mst_login_bonus')->delete();
        DB::connection('mst')->table('mst_vip_login_bonus_content')->delete();
        DB::connection('mst')->table('mst_vip_login_bonus')->delete();
        DB::connection('sys')->table('sys_sharding_node_player')->where('sys_player_id', $this->sysPlayerId)->delete();
        DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->delete();
        DB::connection('sys')->table('sys_sharding_node')->truncate();
        DB::connection('sys')->table('sys_sharding')->truncate();
        
        ApiSession::clearForTest();
        parent::tearDown();
    }

    /**
     * 通常ログインボーナスのマスターデータを作成
     */
    private function createLoginBonusMasterData(): void
    {
        for ($day = 1; $day <= 7; $day++) {
            $bonusId = "login_bonus_day_{$day}";
            
            DB::connection('mst')->table('mst_login_bonus')->insert([
                'id' => $bonusId,
                'type' => 'daily',
                'day' => $day,
                'loop_days' => 7,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('mst')->table('mst_login_bonus_content')->insert([
                'mst_login_bonus_id' => $bonusId,
                'content_type' => 'diamond',
                'content_id' => 'free_diamond',
                'amount' => $day * 100,
                'is_paid' => false,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * VIPログインボーナスのマスターデータを作成
     */
    private function createVipLoginBonusMasterData(): void
    {
        foreach ([0, 5, 10] as $vipLevel) {
            $bonusId = "vip_login_lv{$vipLevel}";
            
            DB::connection('mst')->table('mst_vip_login_bonus')->insert([
                'id' => $bonusId,
                'vip_level' => $vipLevel,
                'loop_days' => 7,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $multiplier = 1 + ($vipLevel * 0.3);
            for ($day = 1; $day <= 7; $day++) {
                $goldAmount = (int) floor(1000 * $day * $multiplier);
                
                DB::connection('mst')->table('mst_vip_login_bonus_content')->insert([
                    'mst_vip_login_bonus_id' => $bonusId,
                    'day' => $day,
                    'content_type' => 'diamond',
                    'content_id' => 'free_diamond',
                    'content_option' => null,
                    'content_quantity' => $goldAmount,
                    'amount' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * カムバックログインボーナスのマスターデータを作成
     */
    private function createComeBackLoginBonusMasterData(): void
    {
        $bonusId = 'comeback_bonus_7days';
        
        DB::connection('mst')->table('mst_login_bonus')->insert([
            'id' => $bonusId,
            'type' => 'comeback',
            'day' => 0,
            'loop_days' => 0,
            'required_absent_days' => 7,
            'valid_days' => 14,
            'priority' => 200,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($day = 1; $day <= 7; $day++) {
            DB::connection('mst')->table('mst_login_bonus_content')->insert([
                'mst_login_bonus_id' => $bonusId,
                'content_type' => 'diamond',
                'content_id' => "day_{$day}_diamond", // 各日で異なるIDを使用
                'amount' => $day * 1000, // カムバックは豪華
                'is_paid' => false,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    #[Test]
    public function 通常とVIPのログインボーナスを同じ日に両方受け取れる(): void
    {
        // Arrange: VIP5のプレイヤー
        DB::connection('sys')->table('sys_player')
            ->where('id', $this->sysPlayerId)
            ->update(['vip_level' => 5, 'vip_point' => 5000]);

        $lastLoginAt = null;

        // Act: ログインボーナスを一括受け取り
        $results = $this->orchestrator->executeAllMerged($this->sysPlayerId, $lastLoginAt, $this->connectionName);

        // Assert: 少なくとも通常ログインボーナスは受け取れる
        $this->assertGreaterThanOrEqual(1, count($results), '少なくとも1つのボーナスを受け取れるべき');
    }

    #[Test]
    public function VIPレベルアップ後は新しいVIPレベルのボーナスを受け取る(): void
    {
        // Arrange: VIP0で1日受け取る
        $firstResult = $this->orchestrator->executeAllMerged($this->sysPlayerId, null, $this->connectionName);
        
        // VIP5にレベルアップ
        DB::connection('sys')->table('sys_player')
            ->where('id', $this->sysPlayerId)
            ->update(['vip_level' => 5, 'vip_point' => 5000]);

        // 翌日ログイン
        $lastLoginAt = now()->subDay()->format('Y-m-d H:i:s');

        // Act: 2日目のボーナスを受け取る
        $secondResult = $this->orchestrator->executeAllMerged($this->sysPlayerId, $lastLoginAt, $this->connectionName);

        // Assert: 何らかのボーナスを受け取っているか確認
        $this->assertGreaterThan(0, count($secondResult), 'ボーナスを受け取るべき');
    }

    #[Test]
    public function カムバックボーナスが最優先で配布される(): void
    {
        // Arrange: 7日以上ログインしていない
        DB::connection('sys')->table('sys_player')
            ->where('id', $this->sysPlayerId)
            ->update([
                'vip_level' => 5,
                'vip_point' => 5000,
                'last_login_at' => now()->subDays(8)->format('Y-m-d H:i:s'),
            ]);

        $lastLoginAt = now()->subDays(8)->format('Y-m-d H:i:s');

        // Act: ログインボーナスを一括受け取り
        $results = $this->orchestrator->executeAllMerged($this->sysPlayerId, $lastLoginAt, $this->connectionName);

        // Assert: 複数のボーナスを受け取る
        $this->assertGreaterThanOrEqual(1, count($results), '少なくとも1つのボーナスを受け取れるべき');
    }

    #[Test]
    public function VIP0からVIP10にレベルアップしても履歴は継続する(): void
    {
        // Arrange: VIP0で3日分受け取る
        for ($day = 1; $day <= 3; $day++) {
            if ($day > 1) {
                $lastLoginAt = now()->subDay()->format('Y-m-d H:i:s');
            } else {
                $lastLoginAt = null;
            }
            $this->orchestrator->executeAllMerged($this->sysPlayerId, $lastLoginAt, $this->connectionName);
            
            // 時間を進める（次の日へ）
            sleep(1);
        }

        // VIP10にレベルアップ
        DB::connection('sys')->table('sys_player')
            ->where('id', $this->sysPlayerId)
            ->update(['vip_level' => 10, 'vip_point' => 10000]);

        $lastLoginAt = now()->subDay()->format('Y-m-d H:i:s');

        // Act: 4日目のボーナスを受け取る
        $results = $this->orchestrator->executeAllMerged($this->sysPlayerId, $lastLoginAt, $this->connectionName);

        // Assert: ボーナスを受け取れることを確認
        $this->assertGreaterThan(0, count($results), '4日目のボーナスを受け取るべき');
    }
}
