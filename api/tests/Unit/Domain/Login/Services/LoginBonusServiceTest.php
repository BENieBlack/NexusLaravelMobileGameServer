<?php

namespace Tests\Unit\Domain\Login\Services;

use App\Domain\Login\Services\LoginBonusService;
use App\Models\Mst\MstLoginBonus;
use App\Models\Mst\MstLoginBonusContent;
use App\Persistence\ApiSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * LoginBonusServiceのテスト
 *
 * ログインボーナスの配布ロジックをテスト
 */
class LoginBonusServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId = 1;

    private LoginBonusService $loginBonusService;

    protected function setUp(): void
    {
        parent::setUp();

        // テストプレイヤーとシャーディング情報を作成
        $this->createTestPlayer();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->loginBonusService = app(LoginBonusService::class);

        // テスト用のログインボーナスマスターを作成（7日間ループ）
        $this->createLoginBonusMasterData();
    }

    /**
     * テストプレイヤーとシャーディング情報を作成
     */
    private function createTestPlayer(): void
    {
        // sys_shardingを作成（存在しなければ）
        $shardingId = DB::connection('sys')->table('sys_sharding')
            ->where('name', 'trx_sharding')
            ->value('id');

        if (! $shardingId) {
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

        if (! $nodeId) {
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

        // sys_playerを作成
        DB::connection('sys')->table('sys_player')->insert([
            'id' => $this->sysPlayerId,
            'uuid' => 'test-uuid-'.uniqid(),
            'my_id' => 'TEST0001',
            'name' => 'Test Player',
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
        DB::connection('mst')->table('mst_login_bonus_content')->delete();
        DB::connection('mst')->table('mst_login_bonus')->delete();
        DB::connection('sys')->table('sys_sharding_node_player')->where('sys_player_id', $this->sysPlayerId)->delete();
        DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->delete();

        ApiSession::clearForTest();
        parent::tearDown();
    }

    /**
     * テスト用のログインボーナスマスターデータを作成
     * 7日間ループで、各日にアイテムを配布（シンプルなテスト用）
     */
    private function createLoginBonusMasterData(): void
    {
        for ($day = 1; $day <= 7; $day++) {
            $bonusId = "login_bonus_day_{$day}";

            MstLoginBonus::create([
                'id' => $bonusId,
                'day' => $day,
                'loop_days' => 7,
                'is_active' => true,
            ]);

            // アイテム報酬のみ（連続日数カウントを正確にするため）
            MstLoginBonusContent::create([
                'mst_login_bonus_id' => $bonusId,
                'content_type' => 'item',
                'content_id' => 'item_potion_001',
                'amount' => $day * 10, // 1日目10個、2日目20個...
                'is_paid' => false,
                'sort_order' => 1,
            ]);
        }

        // キャッシュをクリア
        $this->refreshMstCache();
    }

    #[Test]
    public function 初回ログイン時にログインボーナスが配布される(): void
    {
        $now = CarbonImmutable::parse('2026-04-20 10:00:00', 'UTC');
        ClockUtility::setNow($now);

        // 初回ログイン（lastLoginAt = null）
        $result = $this->loginBonusService->process(
            $this->sysPlayerId,
            null,
            'trx1'
        );

        // 1日目の報酬が配布されることを確認
        $this->assertCount(1, $result); // 1日目はアイテムのみ
        $this->assertSame('item', $result[0]->getType()->value);
        $this->assertSame('item_potion_001', $result[0]->getId());
        $this->assertSame(10, $result[0]->getAmount());

        // 履歴が記録されていることを確認
        $history = DB::connection('trx1')
            ->table('trx_login_bonus_history')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('received_date', 'LIKE', '2026-04-20')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('login_bonus_day_1', $history->mst_login_bonus_id);
    }

    #[Test]
    public function 同日2回目のログインではログインボーナスが配布されない(): void
    {
        $today = CarbonImmutable::parse('2026-04-20 10:00:00', 'UTC');
        ClockUtility::setNow($today);

        // 1回目のログイン
        $result1 = $this->loginBonusService->process(
            $this->sysPlayerId,
            null
        );
        $this->assertCount(1, $result1);

        // 同日の2回目のログイン（lastLoginAt = 今日の午前中）
        $lastLoginAt = $today->setTime(9, 0, 0)->toDateTimeString();
        ClockUtility::setNow($today->setTime(15, 0, 0)); // 同日の午後
        $result2 = $this->loginBonusService->process(
            $this->sysPlayerId,
            $lastLoginAt
        );

        // 配布されないことを確認
        $this->assertEmpty($result2);

        ClockUtility::reset();
    }

    #[Test]
    public function test_連続ログイン2日目で正しい報酬が配布される(): void
    {
        $day1 = CarbonImmutable::parse('2026-04-20 10:00:00', 'UTC');
        ClockUtility::setNow($day1);

        // 1日目
        $this->loginBonusService->process(
            $this->sysPlayerId,
            null
        );

        // 2日目（翌日）
        $day2 = $day1->addDay();
        ClockUtility::setNow($day2);
        $lastLoginAt = $day1->toDateTimeString();

        $result = $this->loginBonusService->process(
            $this->sysPlayerId,
            $lastLoginAt
        );

        // 2日目の報酬が配布されることを確認
        $this->assertCount(1, $result); // 2日目はアイテムのみ
        $this->assertSame('item', $result[0]->getType()->value);
        $this->assertSame(20, $result[0]->getAmount()); // 2日目は20個

        ClockUtility::reset();
    }

    #[Test]
    public function test_7日目に複数報酬が配布される(): void
    {
        // 既存の7日目データを無効化
        DB::connection('mst')
            ->table('mst_login_bonus')
            ->where('day', 7)
            ->update(['is_active' => false]);

        // 7日目用の特別なマスターデータを作成
        $bonusId = 'login_bonus_day_7_special';
        MstLoginBonus::create([
            'id' => $bonusId,
            'day' => 7,
            'loop_days' => 7,
            'is_active' => true,
        ]);

        MstLoginBonusContent::create([
            'mst_login_bonus_id' => $bonusId,
            'content_type' => 'item',
            'content_id' => 'item_potion_001',
            'amount' => 70,
            'is_paid' => false,
            'sort_order' => 1,
        ]);

        MstLoginBonusContent::create([
            'mst_login_bonus_id' => $bonusId,
            'content_type' => 'diamond',
            'content_id' => 'diamond',
            'amount' => 100,
            'is_paid' => false,
            'sort_order' => 2,
        ]);

        $this->refreshMstCache();

        $currentDay = CarbonImmutable::parse('2026-04-20 10:00:00', 'UTC');

        // 1日目〜6日目まで連続ログイン
        for ($i = 1; $i <= 6; $i++) {
            ClockUtility::setNow($currentDay);
            $lastLoginAt = $i === 1 ? null : $currentDay->subDay()->toDateTimeString();
            $this->loginBonusService->process(
                $this->sysPlayerId,
                $lastLoginAt
            );
            $currentDay = $currentDay->addDay();
        }

        // 7日目
        ClockUtility::setNow($currentDay);
        $lastLoginAt = $currentDay->subDay()->toDateTimeString();
        $result = $this->loginBonusService->process(
            $this->sysPlayerId,
            $lastLoginAt
        );

        // 7日目は2つの報酬（アイテム + ダイヤ）
        $this->assertCount(2, $result);

        // アイテム報酬
        $this->assertSame('item', $result[0]->getType()->value);
        $this->assertSame(70, $result[0]->getAmount()); // 7日目は70個

        // ダイヤ報酬
        $this->assertSame('diamond', $result[1]->getType()->value);
        $this->assertSame(100, $result[1]->getAmount());

        ClockUtility::reset();
    }

    #[Test]
    public function test_履歴が複数報酬ごとに記録される(): void
    {
        // 既存の1日目データを無効化
        DB::connection('mst')
            ->table('mst_login_bonus')
            ->where('day', 1)
            ->update(['is_active' => false]);

        // 複数報酬のマスターデータを作成
        $bonusId = 'login_bonus_multi_reward';
        MstLoginBonus::create([
            'id' => $bonusId,
            'day' => 1,
            'loop_days' => 7,
            'is_active' => true,
        ]);

        MstLoginBonusContent::create([
            'mst_login_bonus_id' => $bonusId,
            'content_type' => 'item',
            'content_id' => 'item_potion_001',
            'amount' => 50,
            'is_paid' => false,
            'sort_order' => 1,
        ]);

        MstLoginBonusContent::create([
            'mst_login_bonus_id' => $bonusId,
            'content_type' => 'diamond',
            'content_id' => 'diamond',
            'amount' => 100,
            'is_paid' => false,
            'sort_order' => 2,
        ]);

        $this->refreshMstCache();

        $currentDay = CarbonImmutable::parse('2026-04-20 10:00:00', 'UTC');
        ClockUtility::setNow($currentDay);

        // ログイン
        $this->loginBonusService->process(
            $this->sysPlayerId,
            null
        );

        // 履歴を確認（アイテムとダイヤの2件）
        $histories = DB::connection('trx1')
            ->table('trx_login_bonus_history')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('received_date', $currentDay->toDateString())
            ->get();

        $this->assertCount(2, $histories);

        // アイテム履歴
        $itemHistory = $histories->firstWhere('reward_type', 'item');
        $this->assertNotNull($itemHistory);
        $this->assertSame('item_potion_001', $itemHistory->reward_id);
        $this->assertSame(50, $itemHistory->reward_amount);

        // ダイヤ履歴
        $diamondHistory = $histories->firstWhere('reward_type', 'diamond');
        $this->assertNotNull($diamondHistory);
        $this->assertSame('diamond', $diamondHistory->reward_id);
        $this->assertSame(100, $diamondHistory->reward_amount);

        ClockUtility::reset();
    }

    #[Test]
    public function test_8日目は1日目にループする(): void
    {
        $currentDay = CarbonImmutable::parse('2026-04-20 10:00:00', 'UTC');

        // 1日目〜7日目まで連続ログイン
        for ($i = 1; $i <= 7; $i++) {
            ClockUtility::setNow($currentDay);
            $lastLoginAt = $i === 1 ? null : $currentDay->subDay()->toDateTimeString();
            $this->loginBonusService->process(
                $this->sysPlayerId,
                $lastLoginAt
            );
            $currentDay = $currentDay->addDay();
        }

        // 8日目
        ClockUtility::setNow($currentDay);
        $lastLoginAt = $currentDay->subDay()->toDateTimeString();
        $result = $this->loginBonusService->process(
            $this->sysPlayerId,
            $lastLoginAt
        );

        // 1日目の報酬と同じになる
        $this->assertCount(1, $result);
        $this->assertSame('item', $result[0]->getType()->value);
        $this->assertSame(10, $result[0]->getAmount()); // 1日目の報酬（10個）

        // 履歴を確認
        $history = DB::connection('trx1')
            ->table('trx_login_bonus_history')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('received_date', $currentDay->toDateString())
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('login_bonus_day_1', $history->mst_login_bonus_id);

        ClockUtility::reset();
    }

    #[Test]
    public function test_連続ログインが途切れた場合は1日目にリセットされる(): void
    {
        $day1 = CarbonImmutable::parse('2026-04-20 10:00:00', 'UTC');

        // 1日目〜3日目まで連続ログイン
        $currentDay = $day1;
        for ($i = 1; $i <= 3; $i++) {
            ClockUtility::setNow($currentDay);
            $lastLoginAt = $i === 1 ? null : $currentDay->subDay()->toDateTimeString();
            $this->loginBonusService->process(
                $this->sysPlayerId,
                $lastLoginAt
            );
            $currentDay = $currentDay->addDay();
        }

        // 2日間ログインしない（5日目にログイン）
        $day5 = $day1->addDays(4);
        ClockUtility::setNow($day5);
        $lastLoginAt = $day1->addDays(2)->toDateTimeString(); // 3日目が最終ログイン

        $result = $this->loginBonusService->process(
            $this->sysPlayerId,
            $lastLoginAt
        );

        // 1日目の報酬にリセットされる
        $this->assertCount(1, $result);
        $this->assertSame('item', $result[0]->getType()->value);
        $this->assertSame(10, $result[0]->getAmount()); // 1日目の報酬（10個）

        ClockUtility::reset();
    }

    #[Test]
    public function test_ut_c0時を境界として判定される(): void
    {
        // 4月20日 23:59:59にログイン
        $day1Evening = CarbonImmutable::parse('2026-04-20 23:59:59', 'UTC');
        ClockUtility::setNow($day1Evening);
        $result1 = $this->loginBonusService->process(
            $this->sysPlayerId,
            null
        );
        $this->assertCount(1, $result1);

        // 4月21日 00:00:01にログイン（翌日）
        $day2Morning = CarbonImmutable::parse('2026-04-21 00:00:01', 'UTC');
        ClockUtility::setNow($day2Morning);
        $lastLoginAt = $day1Evening->toDateTimeString();

        $result2 = $this->loginBonusService->process(
            $this->sysPlayerId,
            $lastLoginAt
        );

        // 翌日として判定され、2日目の報酬が配布される
        $this->assertCount(1, $result2);
        $this->assertSame(20, $result2[0]->getAmount()); // 2日目の報酬（20個）

        ClockUtility::reset();
    }

    #[Test]
    public function ログインボーナスが設定されていない場合は何も配布されない(): void
    {
        // すべてのログインボーナスを無効化
        DB::connection('mst')
            ->table('mst_login_bonus')
            ->update(['is_active' => false]);

        $this->refreshMstCache();

        $now = CarbonImmutable::parse('2026-04-20 10:00:00', 'UTC');
        ClockUtility::setNow($now);

        $result = $this->loginBonusService->process(
            $this->sysPlayerId,
            null
        );

        // 何も配布されない
        $this->assertEmpty($result);

        ClockUtility::reset();
    }

    #[Test]
    public function 報酬が設定されていない日は何も配布されない(): void
    {
        // 1日目の報酬内容を削除
        DB::connection('mst')
            ->table('mst_login_bonus_content')
            ->where('mst_login_bonus_id', 'login_bonus_day_1')
            ->delete();

        $this->refreshMstCache();

        $now = CarbonImmutable::parse('2026-04-20 10:00:00', 'UTC');
        ClockUtility::setNow($now);

        $result = $this->loginBonusService->process(
            $this->sysPlayerId,
            null
        );

        // 何も配布されない
        $this->assertEmpty($result);

        ClockUtility::reset();
    }
}
