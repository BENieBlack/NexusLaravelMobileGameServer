<?php

namespace Tests\Feature\Recording;

use App\Persistence\ApiSession;
use App\Repositories\Sys\SysPlayerRepository;
use Database\Seeders\Recording\Scenarios\RecordingLoginBonusProgressSeeder;
use Database\Seeders\Recording\Scenarios\RecordingLoginBonusSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;

/**
 * RecordingLoginBonusProgressSeeder（ログインボーナスのカレンダー撮影用。既存プレイヤーの状態を整える）のテスト
 *
 * シナリオごとに、シード後の list / login / receive の結果が README の表のとおりになることを見る。
 * 設計: docs/design/login-bonus-calendar/db.md 6 節
 */
class RecordingLoginBonusProgressTest extends RecordingTestCase
{
    private string $myId;

    private int $playerId;

    private string $token;

    private string $connection;

    public function beginDatabaseTransaction(): void
    {
        // サインアップ・API が自前でトランザクションを張るため自動ラップしない（tearDown で片付ける）
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setDayStartTime('04:00:00');
        ClockUtility::setNow('2026-10-15 12:00:00');

        ['player' => $player, 'token' => $this->token] = $this->signUpPlayer();
        $this->playerId = $player->id;
        $this->myId = $player->my_id;
        $this->connection = $this->playerConnection($this->playerId);
        $this->deleteInitialGrant($this->playerId);

        // 通常は動画用のシナリオ、VIP は全レベルに 7 日の行を持つテスト用マスター
        DB::connection('mst')->table('mst_vip_login_bonus_content')->delete();
        DB::connection('mst')->table('mst_vip_login_bonus')->delete();
        $this->runSeeder(RecordingLoginBonusSeeder::class);
        for ($level = 0; $level <= 20; $level++) {
            DB::connection('mst')->table('mst_vip_login_bonus')->insert([
                'id' => "rec_vip_lv{$level}", 'vip_level' => $level, 'loop_days' => 7, 'is_active' => true,
            ]);
            for ($day = 1; $day <= 7; $day++) {
                DB::connection('mst')->table('mst_vip_login_bonus_content')->insert([
                    'mst_vip_login_bonus_id' => "rec_vip_lv{$level}", 'day' => $day,
                    'content_type' => 'item', 'content_mst_id' => 'free_diamond',
                    'content_quantity' => $level * 10 + $day, 'amount' => 1,
                ]);
            }
        }
        $this->refreshMstCache();
    }

    protected function tearDown(): void
    {
        foreach (['trx_login_bonus', 'trx_vip_login_bonus', 'trx_item', 'log_action_vip_login_bonus_receive', 'log_action_login_bonus_receive'] as $table) {
            DB::connection($this->connection)->table($table)->where('sys_player_id', $this->playerId)->delete();
        }
        DB::connection('mst')->table('mst_vip_login_bonus_content')->delete();
        DB::connection('mst')->table('mst_vip_login_bonus')->delete();
        putenv(RecordingLoginBonusProgressSeeder::ENV_PLAYER_MY_ID);
        putenv(RecordingLoginBonusProgressSeeder::ENV_SCENARIO);
        $this->refreshMstCache();
        ClockUtility::reset();
        ClockUtility::setDayStartTime('00:00:00');
        ApiSession::clearForTest();

        parent::tearDown();
    }

    #[Test]
    public function シナリオ1_初回は両段が配られスタンプを再生する(): void
    {
        $this->seedScenario('1');

        $data = $this->login()->json();

        $this->assertSame(1, $data['login_bonus_calendar']['last_received_day']);
        $this->assertTrue($data['login_bonus_calendar']['is_granted_now']);
        $this->assertTrue($data['vip_login_bonus_calendar']['is_granted_now']);
    }

    #[Test]
    public function シナリオ2_前日に3日目なら4日目が配られる(): void
    {
        $this->seedScenario('2');

        $data = $this->login()->json();

        $this->assertSame(4, $data['login_bonus_calendar']['last_received_day']);
        $this->assertSame(4, $data['vip_login_bonus_calendar']['last_received_day']);
        $this->assertNotEmpty($data['login_bonus_list']);
    }

    #[Test]
    public function シナリオ3_同じ日の2回目はlogin_bonus_listが空でスタンプを再生しない(): void
    {
        $this->seedScenario('3');

        $data = $this->login()->json();

        $this->assertSame([], $data['login_bonus_list']);
        foreach (['login_bonus_calendar', 'vip_login_bonus_calendar'] as $key) {
            $this->assertTrue($data[$key]['is_granted_today'], $key);
            $this->assertFalse($data[$key]['is_granted_now'], $key);
            $this->assertSame(4, $data[$key]['last_received_day'], $key);
        }
    }

    #[Test]
    public function シナリオ4_7日目が配られ_シナリオ5_1周後は1日目に戻る(): void
    {
        $this->seedScenario('4');
        $this->assertSame(7, $this->login()->json('login_bonus_calendar.last_received_day'));

        $this->seedScenario('5');
        $this->assertSame(1, $this->login()->json('login_bonus_calendar.last_received_day'));
    }

    #[Test]
    public function シナリオ6_vi_pの段だけnull(): void
    {
        $this->seedScenario('6');

        $data = $this->login()->json();

        $this->assertNotNull($data['login_bonus_calendar']);
        $this->assertNull($data['vip_login_bonus_calendar']);
    }

    #[Test]
    public function シナリオ7_vi_pだけ今日の段でない(): void
    {
        $this->seedScenario('7');

        $data = $this->listCalendar()->json();

        $this->assertTrue($data['login_bonus_calendar']['is_granted_today']);
        $this->assertFalse($data['vip_login_bonus_calendar']['is_granted_today']);
    }

    #[Test]
    public function シナリオ8_再受け取りできる状態になり_receiveで配られる(): void
    {
        $this->seedScenario('8');

        // last_login_at が今日なので、login しても新しい日の分は配られず、再受け取りの前提が崩れない
        $login = $this->login()->json();
        $this->assertSame([], $login['login_bonus_list']);
        $this->assertTrue($login['vip_login_bonus_calendar']['can_re_receive']);

        $vipId = $login['vip_login_bonus_calendar']['mst_vip_login_bonus_id'];
        $this->startFreshRequest();
        $receive = $this->postJson('/api/login_bonus/receive', ['mst_vip_login_bonus_id' => $vipId], $this->authHeaders($this->token));

        $receive->assertOk();
        $this->assertFalse($receive->json('vip_login_bonus_calendar.can_re_receive'));
        $this->assertTrue($receive->json('vip_login_bonus_calendar.is_granted_now'));
    }

    #[Test]
    public function シナリオ_f2_通常のマスターが無効で_vi_pの段だけある(): void
    {
        $this->seedScenario('F2');

        $data = $this->login()->json();

        $this->assertNull($data['login_bonus_calendar']);
        $this->assertNotNull($data['vip_login_bonus_calendar']);
        $this->assertTrue($data['vip_login_bonus_calendar']['is_granted_now']);
    }

    #[Test]
    public function シナリオ_g_両段とも今日の段でない(): void
    {
        $this->seedScenario('G');

        $data = $this->listCalendar()->json();

        $this->assertFalse($data['login_bonus_calendar']['is_granted_today']);
        $this->assertFalse($data['vip_login_bonus_calendar']['is_granted_today']);
    }

    #[Test]
    public function 別のシナリオで上書きでき_戻すと通常のマスターが有効に戻る(): void
    {
        $this->seedScenario('F2');
        $this->assertNull($this->listCalendar()->json('login_bonus_calendar'));

        // 通常を有効にするシナリオで上書きすると、daily_login も有効に戻る
        $this->seedScenario('1');
        $this->assertNotNull($this->listCalendar()->json('login_bonus_calendar'));
    }

    #[Test]
    public function 同じ指定で2回流しても状態は変わらない(): void
    {
        $this->seedScenario('2');
        $first = $this->snapshot();

        $this->seedScenario('2');

        $this->assertSame($first, $this->snapshot());
    }

    #[Test]
    public function my_idが無い_番号が不明_該当プレイヤーがいないときは何も書かない(): void
    {
        $before = $this->snapshot();

        // my_id が無い
        putenv(RecordingLoginBonusProgressSeeder::ENV_PLAYER_MY_ID);
        putenv(RecordingLoginBonusProgressSeeder::ENV_SCENARIO.'=2');
        $this->runSeeder(RecordingLoginBonusProgressSeeder::class);
        // 番号が不明
        $this->seedScenario('99');
        // 該当するプレイヤーがいない
        putenv(RecordingLoginBonusProgressSeeder::ENV_PLAYER_MY_ID.'=NOSUCHID');
        putenv(RecordingLoginBonusProgressSeeder::ENV_SCENARIO.'=2');
        $this->runSeeder(RecordingLoginBonusProgressSeeder::class);

        $this->assertSame($before, $this->snapshot());
    }

    #[Test]
    public function 許可されていない環境では動かない(): void
    {
        putenv(RecordingLoginBonusProgressSeeder::ENV_PLAYER_MY_ID.'='.$this->myId);
        putenv(RecordingLoginBonusProgressSeeder::ENV_SCENARIO.'=2');
        $this->app->instance('env', 'production');

        $this->expectException(\RuntimeException::class);

        $this->app->make(RecordingLoginBonusProgressSeeder::class)->run();
    }

    // ------------------------------------------------------------------

    private function seedScenario(string $scenario): void
    {
        putenv(RecordingLoginBonusProgressSeeder::ENV_PLAYER_MY_ID.'='.$this->myId);
        putenv(RecordingLoginBonusProgressSeeder::ENV_SCENARIO.'='.$scenario);
        $this->runSeeder(RecordingLoginBonusProgressSeeder::class);
        $this->refreshMstCache();
    }

    private function login(): TestResponse
    {
        $this->startFreshRequest();

        return $this->postJson('/api/auth/login', [], $this->authHeaders($this->token));
    }

    private function listCalendar(): TestResponse
    {
        $this->startFreshRequest();

        return $this->postJson('/api/login_bonus/list', [], $this->authHeaders($this->token));
    }

    /** 本番ではリクエストごとに新しいプロセス。テストでも前のリクエストのキュー・キャッシュを捨てる */
    private function startFreshRequest(): void
    {
        ApiSession::clearForTest();
        app(QueryManager::class)->clear();
        app(SysPlayerRepository::class)->forgetCachedModels();
        // Route が Controller（と、その中の UseCase・Service が持つ前のリクエストの状態）を使い回さないようにする
        foreach (app('router')->getRoutes() as $route) {
            $route->flushController();
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'normal' => DB::connection($this->connection)->table('trx_login_bonus')->where('sys_player_id', $this->playerId)->get()->map(fn ($r) => (array) $r)->all(),
            'vip' => DB::connection($this->connection)->table('trx_vip_login_bonus')->where('sys_player_id', $this->playerId)->get()->map(fn ($r) => (array) $r)->all(),
            'player' => (array) DB::connection('sys')->table('sys_player')->where('id', $this->playerId)->select('last_login_at', 'vip_point')->first(),
        ];
    }
}
