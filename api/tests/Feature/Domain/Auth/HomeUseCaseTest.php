<?php

namespace Tests\Feature\Domain\Auth;

use App\Models\Mst\MstLoginBonus;
use App\Models\Mst\MstLoginBonusContent;
use App\Models\Sys\SysPlayer;
use Carbon\CarbonImmutable;
use Database\Seeders\SysShardingSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * HomeUseCaseのテスト（ログインボーナス機能含む）
 *
 * /auth/loginエンドポイントでのログインボーナス配布をテスト
 */
class HomeUseCaseTest extends TestCase
{
    use RefreshMultipleDatabases;

    private SysPlayer $testPlayer;

    private string $accessToken;

    /**
     * Feature tests don't use database transactions to avoid conflicts with application-level transactions
     */
    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            // Run migrate:fresh for each connection with its specific path
            foreach ($this->connectionsToMigrate() as $connection => $path) {
                // First, drop all tables in the database
                $this->artisan('migrate:reset', [
                    '--database' => $connection,
                    '--path' => $path,
                    '--force' => true,
                ]);

                // Then run fresh migrations
                $this->artisan('migrate', [
                    '--database' => $connection,
                    '--path' => $path,
                    '--force' => true,
                ]);
            }

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        // Feature tests: DON'T use database transactions - clean up in tearDown instead
    }

    protected function setUp(): void
    {
        parent::setUp();

        // シャーディング設定を作成（sign_upに必要）
        $this->seed(SysShardingSeeder::class);

        // テストプレイヤーを作成
        $this->createTestPlayer();

        // テスト用のログインボーナスマスターを作成
        $this->createLoginBonusMasterData();
    }

    protected function tearDown(): void
    {
        // テストデータをクリア
        DB::connection('trx1')->table('trx_login_bonus_history')->truncate();
        DB::connection('trx1')->table('trx_unit')->truncate();
        DB::connection('trx1')->table('trx_item')->truncate();
        DB::connection('trx1')->table('trx_wallet')->truncate();
        DB::connection('mst')->table('mst_login_bonus_content')->delete();
        DB::connection('mst')->table('mst_login_bonus')->delete();

        parent::tearDown();
    }

    /**
     * テストプレイヤーを作成
     */
    private function createTestPlayer(): void
    {
        // sign_upでプレイヤーを作成
        $response = $this->postJson('/api/auth/sign_up', [
            'device_id' => 'test-device-'.uniqid(),
            'device_info' => [
                'os' => 'iOS',
                'os_version' => '17.0',
                'model' => 'iPhone 15 Pro',
                'app_version' => '1.0.0',
            ],
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->accessToken = $data['dto_token']['access_token'];
        $myId = $data['sys_player']['my_id'];

        $this->testPlayer = SysPlayer::where('my_id', $myId)->first();

        // シャーディングノードへの割り当てを手動で作成（sign_upでは作成されないため）
        $sharding = DB::connection('sys')->table('sys_sharding')
            ->where('name', 'trx_sharding')
            ->first();

        $node = DB::connection('sys')->table('sys_sharding_node')
            ->where('sys_sharding_id', $sharding->id)
            ->where('node_name', 'node1')
            ->first();

        DB::connection('sys')->table('sys_sharding_node_player')->insert([
            'sys_sharding_node_id' => $node->id,
            'sys_player_id' => $this->testPlayer->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * テスト用のログインボーナスマスターデータを作成
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

            // アイテム報酬
            MstLoginBonusContent::create([
                'mst_login_bonus_id' => $bonusId,
                'content_type' => 'item',
                'content_id' => 'item_potion_001',
                'amount' => $day * 10,
                'is_paid' => false,
                'sort_order' => 1,
            ]);

            // 7日目のみダイヤ報酬追加
            if ($day === 7) {
                MstLoginBonusContent::create([
                    'mst_login_bonus_id' => $bonusId,
                    'content_type' => 'diamond',
                    'content_id' => 'diamond',
                    'amount' => 100,
                    'is_paid' => false,
                    'sort_order' => 2,
                ]);
            }
        }

        $this->refreshMstCache();
    }

    #[Test]
    public function test_初回ログインでログインボーナスが配布される(): void
    {
        // last_login_atをnullにリセット（初回ログイン状態にする）
        $this->testPlayer->update(['last_login_at' => null]);

        // ログインAPI呼び出し
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->accessToken,
        ])->postJson('/api/auth/login');

        $response->assertOk();
        $data = $response->json('data');

        // レスポンス構造を確認
        $this->assertArrayHasKey('sys_player', $data);
        $this->assertArrayHasKey('trx_unit_list', $data);
        $this->assertArrayHasKey('trx_item_list', $data);
        $this->assertArrayHasKey('trx_wallet_list', $data);
        $this->assertArrayHasKey('login_bonus_list', $data);

        // ログインボーナスが配布されていることを確認
        $loginBonusList = $data['login_bonus_list'];
        $this->assertCount(1, $loginBonusList); // 1日目はアイテムのみ

        $this->assertSame('item', $loginBonusList[0]['type']);
        $this->assertSame('item_potion_001', $loginBonusList[0]['id']);
        $this->assertSame(10, $loginBonusList[0]['amount']);

        // 履歴が記録されていることを確認
        $history = DB::connection('trx1')
            ->table('trx_login_bonus_history')
            ->where('sys_player_id', $this->testPlayer->id)
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('login_bonus_day_1', $history->mst_login_bonus_id);
    }

    #[Test]
    public function test_同日2回目のログインではログインボーナスが配布されない(): void
    {
        // 1回目のログイン
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->accessToken,
        ])->postJson('/api/auth/login');

        $response1->assertOk();
        $data1 = $response1->json('data');
        $this->assertNotEmpty($data1['login_bonus_list']);

        // 2回目のログイン（同日）
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->accessToken,
        ])->postJson('/api/auth/login');

        $response2->assertOk();
        $data2 = $response2->json('data');

        // ログインボーナスが空であることを確認
        $this->assertEmpty($data2['login_bonus_list']);
    }

    #[Test]
    public function test_翌日ログインで2日目の報酬が配布される(): void
    {
        // 1日目のログイン
        $day1 = CarbonImmutable::parse('2026-04-20 10:00:00', 'UTC');
        $this->testPlayer->update(['last_login_at' => null]);

        $this->travelTo($day1);

        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->accessToken,
        ])->postJson('/api/auth/login');

        $response1->assertOk();
        $data1 = $response1->json('data');
        $this->assertCount(1, $data1['login_bonus_list']);
        $this->assertSame(10, $data1['login_bonus_list'][0]['amount']); // 1日目: 10個

        // 2日目のログイン
        $day2 = $day1->addDay();
        $this->travelTo($day2);

        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->accessToken,
        ])->postJson('/api/auth/login');

        $response2->assertOk();
        $data2 = $response2->json('data');

        $this->assertCount(1, $data2['login_bonus_list']);
        $this->assertSame('item', $data2['login_bonus_list'][0]['type']);
        $this->assertSame(20, $data2['login_bonus_list'][0]['amount']); // 2日目: 20個

        $this->travelBack();
    }

    #[Test]
    public function test_7日目に複数報酬が配布される(): void
    {
        $currentDay = CarbonImmutable::parse('2026-04-20 10:00:00', 'UTC');

        // 1日目〜7日目まで連続ログイン
        for ($i = 1; $i <= 7; $i++) {
            $this->travelTo($currentDay);

            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken,
            ])->postJson('/api/auth/login');

            $response->assertOk();
            $data = $response->json('data');

            if ($i === 7) {
                // 7日目は2つの報酬（アイテム + ダイヤ）
                $this->assertCount(2, $data['login_bonus_list']);

                $this->assertSame('item', $data['login_bonus_list'][0]['type']);
                $this->assertSame(70, $data['login_bonus_list'][0]['amount']);

                $this->assertSame('diamond', $data['login_bonus_list'][1]['type']);
                $this->assertSame(100, $data['login_bonus_list'][1]['amount']);
            } else {
                // 1〜6日目はアイテムのみ
                $this->assertCount(1, $data['login_bonus_list']);
                $this->assertSame($i * 10, $data['login_bonus_list'][0]['amount']);
            }

            $currentDay = $currentDay->addDay();
        }

        $this->travelBack();
    }

    #[Test]
    public function test_8日目は1日目にループする(): void
    {
        $currentDay = CarbonImmutable::parse('2026-04-20 10:00:00', 'UTC');

        // 1日目〜8日目まで連続ログイン
        for ($i = 1; $i <= 8; $i++) {
            $this->travelTo($currentDay);

            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken,
            ])->postJson('/api/auth/login');

            $response->assertOk();
            $data = $response->json('data');

            if ($i === 8) {
                // 8日目は1日目の報酬にループ
                $this->assertCount(1, $data['login_bonus_list']);
                $this->assertSame('item', $data['login_bonus_list'][0]['type']);
                $this->assertSame(10, $data['login_bonus_list'][0]['amount']); // 1日目と同じ
            }

            $currentDay = $currentDay->addDay();
        }

        $this->travelBack();
    }

    #[Test]
    public function test_連続ログインが途切れると1日目にリセットされる(): void
    {
        $day1 = CarbonImmutable::parse('2026-04-20 10:00:00', 'UTC');

        // 1日目〜3日目まで連続ログイン
        $currentDay = $day1;
        for ($i = 1; $i <= 3; $i++) {
            $this->travelTo($currentDay);

            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken,
            ])->postJson('/api/auth/login');

            $response->assertOk();
            $currentDay = $currentDay->addDay();
        }

        // 2日間飛ばして5日目にログイン
        $day5 = $day1->addDays(4);
        $this->travelTo($day5);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->accessToken,
        ])->postJson('/api/auth/login');

        $response->assertOk();
        $data = $response->json('data');

        // 1日目にリセットされる
        $this->assertCount(1, $data['login_bonus_list']);
        $this->assertSame(10, $data['login_bonus_list'][0]['amount']); // 1日目の報酬

        $this->travelBack();
    }

    #[Test]
    public function test_ログインボーナスが無効の場合は何も配布されない(): void
    {
        // すべてのログインボーナスを無効化
        DB::connection('mst')
            ->table('mst_login_bonus')
            ->update(['is_active' => false]);

        $this->refreshMstCache();

        // ログイン
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->accessToken,
        ])->postJson('/api/auth/login');

        $response->assertOk();
        $data = $response->json('data');

        // ログインボーナスが空
        $this->assertEmpty($data['login_bonus_list']);
    }

    #[Test]
    public function test_last_login_atが更新される(): void
    {
        $beforeLoginAt = $this->testPlayer->last_login_at;

        // ログイン
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->accessToken,
        ])->postJson('/api/auth/login');

        $response->assertOk();

        // last_login_atが更新されていることを確認
        $this->testPlayer->refresh();
        $this->assertNotEquals($beforeLoginAt, $this->testPlayer->last_login_at);
        $this->assertNotNull($this->testPlayer->last_login_at);
    }
}
