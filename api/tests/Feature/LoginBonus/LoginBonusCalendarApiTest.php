<?php

namespace Tests\Feature\LoginBonus;

use App\Exceptions\GameErrorCode;
use App\Persistence\ApiSession;
use App\Repositories\Sys\SysPlayerRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;
use Nexus\Core\Utilities\ClockUtility;
use NexusVip\Services\VipLevelService;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * ログインボーナスのカレンダー表示（login の新キー・POST /login_bonus/list）と
 * VIP の再受け取り（POST /login_bonus/receive）のエンドポイントのテスト
 *
 * 設計: docs/design/login-bonus-calendar/{db,api,sequence}.md
 *
 * マスターはテスト用に作り直す。通常は 3 日ループ（3 日目は報酬行なし）、
 * VIP は全レベルに 3 日ループ（レベルごとに配る量が違う）を用意する。
 * 時刻は ClockUtility で固定し、日またぎは固定時刻を進めて作る。
 */
class LoginBonusCalendarApiTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const NORMAL_ID = 'cal_daily';

    /** VIP 側の ID は "cal_vip_lv{レベル}"（全レベル分を作る） */
    private const VIP_ID_PREFIX = 'cal_vip_lv';

    private const DAY1 = '2026-10-15 12:00:00';

    private const DAY2 = '2026-10-16 12:00:00';

    private int $sysPlayerId;

    private string $accessToken;

    private string $connection;

    public function beginDatabaseTransaction(): void
    {
        // UseCaseが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setDayStartTime('04:00:00');
        ClockUtility::setNow(self::DAY1);

        $this->cleanUpMaster();
        $this->createMaster();
        $this->refreshMstCache();

        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        $this->accessToken = $token;
        $this->connection = $this->playerConnection($this->sysPlayerId);

        // 空の新規プレイヤーを前提にするので、初期付与の行を外す
        $this->deleteInitialGrant($this->sysPlayerId);
        ApiSession::clearForTest();
        app(QueryManager::class)->clear();
    }

    protected function tearDown(): void
    {
        foreach (['trx_login_bonus', 'trx_vip_login_bonus', 'trx_item', 'trx_wallet', 'trx_wallet_balance', 'log_action_vip_login_bonus_receive', 'log_action_login_bonus_receive'] as $table) {
            DB::connection($this->connection)->table($table)
                ->where('sys_player_id', $this->sysPlayerId)->delete();
        }

        $this->cleanUpMaster();
        $this->refreshMstCache();
        ApiSession::clearForTest();
        ClockUtility::reset();
        ClockUtility::setDayStartTime('00:00:00');
        app(QueryManager::class)->clear();

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // login の新キー
    // ------------------------------------------------------------------

    #[Test]
    public function 初回のloginで両段の新キーが返り_配った分のスタンプを再生する段になる(): void
    {
        $response = $this->login();

        $response->assertOk();
        $normal = $response->json('login_bonus_calendar');
        $vip = $response->json('vip_login_bonus_calendar');

        // 通常の段: 配布の後に読むので最後に配った日数は 1、今回配ったので is_granted_now は真
        $this->assertSame(self::NORMAL_ID, $normal['mst_login_bonus_id']);
        $this->assertSame(3, $normal['loop_days']);
        $this->assertSame(1, $normal['last_received_day']);
        $this->assertTrue($normal['is_granted_today']);
        $this->assertTrue($normal['is_granted_now']);
        $this->assertCount(3, $normal['day_reward_list']);

        // VIP の段: 今のレベルのマスター。レベルは受取済みの行ではなく今のレベル
        $level = $this->currentVipLevel();
        $this->assertSame(self::VIP_ID_PREFIX.$level, $vip['mst_vip_login_bonus_id']);
        $this->assertSame($level, $vip['vip_level']);
        $this->assertSame(1, $vip['last_received_day']);
        $this->assertTrue($vip['is_granted_today']);
        $this->assertTrue($vip['is_granted_now']);
        // 配ったばかりで、レベルも上がっていないので再受け取りは不可
        $this->assertFalse($vip['can_re_receive']);

        // 既存のキーは残っている
        $this->assertArrayHasKey('login_bonus_list', $response->json());
    }

    #[Test]
    public function 全日の報酬が配る順で並び_報酬行の無い日も空の配列で残る(): void
    {
        $normal = $this->login()->json('login_bonus_calendar');

        $days = array_column($normal['day_reward_list'], 'day');
        $this->assertSame([1, 2, 3], $days);

        // 1 日目: sort_order → content_type → content_mst_id の順（sort_order が同じ行は種類・ID の順）
        $this->assertSame([
            ['type' => 'gold', 'id' => 'gold', 'amount' => 500, 'metadata' => null],
            ['type' => 'item', 'id' => 'item_a', 'amount' => 10, 'metadata' => null],
            ['type' => 'item', 'id' => 'item_b', 'amount' => 20, 'metadata' => null],
        ], $normal['day_reward_list'][0]['reward_list']);
        // content_quantity × amount が配る量、is_paid は metadata に出る
        $this->assertSame(
            ['type' => 'item', 'id' => 'free_diamond', 'amount' => 6, 'metadata' => ['is_paid' => true]],
            $normal['day_reward_list'][1]['reward_list'][0]
        );
        // 報酬行が無い 3 日目も要素は残す
        $this->assertSame([], $normal['day_reward_list'][2]['reward_list']);
    }

    #[Test]
    public function 同じ日の2回目のloginではスタンプを再生せず_今日の分は配布済みのまま(): void
    {
        $this->login()->assertOk();

        $second = $this->login();

        $second->assertOk();
        $this->assertSame([], $second->json('login_bonus_list'));
        foreach (['login_bonus_calendar', 'vip_login_bonus_calendar'] as $key) {
            $this->assertTrue($second->json("{$key}.is_granted_today"), $key);
            $this->assertFalse($second->json("{$key}.is_granted_now"), $key);
            $this->assertSame(1, $second->json("{$key}.last_received_day"), $key);
        }
    }

    #[Test]
    public function 日またぎの翌日はlistで今日の分が未配布になり_loginで2日目が配られる(): void
    {
        $this->login()->assertOk();

        ClockUtility::setNow(self::DAY2);

        // login の前: 日数は 1 のまま、今日の分は未配布（クライアントは last_received_day を今日の日数として使わない）
        $before = $this->listCalendar();
        $before->assertOk();
        $this->assertFalse($before->json('login_bonus_calendar.is_granted_today'));
        $this->assertSame(1, $before->json('login_bonus_calendar.last_received_day'));
        $this->assertFalse($before->json('vip_login_bonus_calendar.is_granted_today'));

        // login の後: 2 日目が配られ、スタンプを再生する段になる
        $after = $this->login();
        $this->assertSame(2, $after->json('login_bonus_calendar.last_received_day'));
        $this->assertTrue($after->json('login_bonus_calendar.is_granted_today'));
        $this->assertTrue($after->json('login_bonus_calendar.is_granted_now'));
        $this->assertSame(2, $after->json('vip_login_bonus_calendar.last_received_day'));
    }

    #[Test]
    public function ゲーム日の境界をまたぐと日付が同じでも別の日になる(): void
    {
        // DAY_START_TIME = 04:00。3:59 は前のゲーム日、4:00 から次のゲーム日
        ClockUtility::setNow('2026-10-16 03:59:00');
        $this->login()->assertOk();

        ClockUtility::setNow('2026-10-16 03:59:30');
        $this->assertTrue($this->listCalendar()->json('login_bonus_calendar.is_granted_today'));

        ClockUtility::setNow('2026-10-16 04:00:00');
        $this->assertFalse($this->listCalendar()->json('login_bonus_calendar.is_granted_today'));
    }

    #[Test]
    public function マスターが無い段のキーは_nullで常に返る(): void
    {
        DB::connection('mst')->table('mst_login_bonus')->update(['is_active' => false]);
        DB::connection('mst')->table('mst_vip_login_bonus')->update(['is_active' => false]);
        $this->refreshMstCache();

        $response = $this->login();

        $response->assertOk();
        $this->assertArrayHasKey('login_bonus_calendar', $response->json());
        $this->assertArrayHasKey('vip_login_bonus_calendar', $response->json());
        $this->assertNull($response->json('login_bonus_calendar'));
        $this->assertNull($response->json('vip_login_bonus_calendar'));
    }

    #[Test]
    public function 通常だけ無効でも_vi_pの段は残る_vi_pだけ無効でも通常の段は残る(): void
    {
        DB::connection('mst')->table('mst_login_bonus')->update(['is_active' => false]);
        $this->refreshMstCache();
        $onlyVip = $this->login();
        $this->assertNull($onlyVip->json('login_bonus_calendar'));
        $this->assertNotNull($onlyVip->json('vip_login_bonus_calendar'));

        DB::connection('mst')->table('mst_login_bonus')->update(['is_active' => true]);
        DB::connection('mst')->table('mst_vip_login_bonus')->update(['is_active' => false]);
        $this->refreshMstCache();
        $onlyNormal = $this->listCalendar();
        $this->assertNotNull($onlyNormal->json('login_bonus_calendar'));
        $this->assertNull($onlyNormal->json('vip_login_bonus_calendar'));
    }

    #[Test]
    public function 未知のcontent_typeの行はその行だけ除かれ_loginは失敗しない(): void
    {
        // 今のレベルの VIP ボーナスの 2 日目に、ResourceType に無い種類の行を足す
        // （配る日は 1 日目なので配布には影響しない。配布の失敗は今のまま）
        $level = $this->currentVipLevel();
        DB::connection('mst')->table('mst_vip_login_bonus_content')->insert([
            'mst_vip_login_bonus_id' => self::VIP_ID_PREFIX.$level,
            'day' => 2,
            'content_type' => 'unknown_type',
            'content_mst_id' => 'x',
            'content_quantity' => 1,
            'amount' => 1,
        ]);
        $this->refreshMstCache();

        $response = $this->login();

        $response->assertOk();
        $day2 = $response->json('vip_login_bonus_calendar.day_reward_list.1');
        $this->assertSame(2, $day2['day']);
        // 正しい行は残り、不正な行だけが無い
        $this->assertCount(1, $day2['reward_list']);
        $this->assertNotSame('unknown_type', $day2['reward_list'][0]['type']);
    }

    #[Test]
    public function vi_pの段はレベル別のマスターを引き_vip_levelは今のレベルを返す(): void
    {
        $this->login()->assertOk();
        $levelBefore = $this->currentVipLevel();

        $this->raiseVipLevel();
        $levelAfter = $this->currentVipLevel();
        $this->assertGreaterThan($levelBefore, $levelAfter);

        $vip = $this->listCalendar()->json('vip_login_bonus_calendar');

        // 受取済みの行は旧レベルのまま。段のレベル・ID・報酬は今のレベルのもの
        $this->assertSame($levelAfter, $vip['vip_level']);
        $this->assertSame(self::VIP_ID_PREFIX.$levelAfter, $vip['mst_vip_login_bonus_id']);
        $this->assertSame($levelAfter * 100 + 1, $vip['day_reward_list'][0]['reward_list'][0]['amount']);
        $this->assertTrue($vip['can_re_receive']);
    }

    // ------------------------------------------------------------------
    // POST /login_bonus/list
    // ------------------------------------------------------------------

    #[Test]
    public function listは書き込まず_is_granted_nowは常に偽で_updated_resourcesを返さない(): void
    {
        $this->login()->assertOk();
        $before = $this->snapshotTables();

        $response = $this->listCalendar();

        $response->assertOk();
        $this->assertSame(['login_bonus_calendar', 'vip_login_bonus_calendar'], array_keys($response->json()));
        $this->assertFalse($response->json('login_bonus_calendar.is_granted_now'));
        $this->assertFalse($response->json('vip_login_bonus_calendar.is_granted_now'));
        $this->assertSame($before, $this->snapshotTables(), 'list は DB を変えない');
    }

    #[Test]
    public function listはマスターが無ければ両方null(): void
    {
        DB::connection('mst')->table('mst_login_bonus')->update(['is_active' => false]);
        DB::connection('mst')->table('mst_vip_login_bonus')->update(['is_active' => false]);
        $this->refreshMstCache();

        $response = $this->listCalendar();

        $response->assertOk();
        $this->assertNull($response->json('login_bonus_calendar'));
        $this->assertNull($response->json('vip_login_bonus_calendar'));
    }

    #[Test]
    public function listは未認証だと401(): void
    {
        $this->postJson('/api/login_bonus/list')->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // POST /login_bonus/receive
    // ------------------------------------------------------------------

    #[Test]
    public function レベルが上がった日に差額の報酬を再受け取りできる(): void
    {
        $this->login()->assertOk();
        $this->raiseVipLevel();
        $level = $this->currentVipLevel();

        $response = $this->receive(self::VIP_ID_PREFIX.$level, 'req-receive-1');

        $response->assertOk();
        // 据え置いた日数（1 日目）の、新しいレベルの報酬が配られる
        $this->assertSame(
            [['type' => 'item', 'id' => 'vip_item', 'amount' => $level * 100 + 1, 'metadata' => null]],
            array_map(
                fn (array $reward) => array_intersect_key($reward, array_flip(['type', 'id', 'amount', 'metadata'])),
                $response->json('login_bonus_list')
            )
        );
        $this->assertArrayHasKey('unique_id', $response->json('login_bonus_list.0'));

        // trx_vip_login_bonus: 今のレベル・据え置きの day・新しい ID
        $trx = $response->json('trx_vip_login_bonus');
        $this->assertSame(self::VIP_ID_PREFIX.$level, $trx['mst_vip_login_bonus_id']);
        $this->assertSame(1, $trx['day']);
        $this->assertSame($level, $trx['vip_level']);
        // sys_player_id・created_at・updated_at は除かれ、4 項目だけ返る
        $this->assertSame(['mst_vip_login_bonus_id', 'day', 'vip_level', 'received_at'], array_keys($trx));

        // 応答の VIP の段: 今回配ったので真、再受け取りは済んだので偽
        $vip = $response->json('vip_login_bonus_calendar');
        $this->assertTrue($vip['is_granted_now']);
        $this->assertTrue($vip['is_granted_today']);
        $this->assertFalse($vip['can_re_receive']);
        $this->assertSame(1, $vip['last_received_day']);
        $this->assertSame($level, $vip['vip_level']);

        // updated_resources は更新した trx だけ（無償分は login の 1 日目の分 1 に再受け取りの分が足された絶対値）。
        // trx_vip_login_bonus は入らない
        $this->assertSame(
            $level * 100 + 1 + 1,
            collect($response->json('updated_resources.trx_item_list'))->firstWhere('mst_item_id', 'vip_item')['free_amount'] ?? null
        );
        $this->assertArrayNotHasKey('trx_vip_login_bonus', $response->json('updated_resources'));
        // 通常の段は返さない
        $this->assertArrayNotHasKey('login_bonus_calendar', $response->json());

        // DB: 履歴は 1 報酬 1 行（login の 1 回目の分と再受け取りの分）
        $row = DB::connection($this->connection)->table('trx_vip_login_bonus')
            ->where('sys_player_id', $this->sysPlayerId)->first();
        $this->assertSame($level, (int) $row->vip_level);
        $this->assertSame(1, (int) $row->day);
        $logs = DB::connection($this->connection)->table('log_action_vip_login_bonus_receive')
            ->where('sys_player_id', $this->sysPlayerId)->where('vip_level', $level)->get();
        $this->assertCount(1, $logs);
        $this->assertSame($level * 100 + 1, (int) $logs[0]->reward_amount);
    }

    #[Test]
    public function received_atの書式を固定する(): void
    {
        $this->login()->assertOk();
        $this->raiseVipLevel();
        $level = $this->currentVipLevel();

        $receivedAt = $this->receive(self::VIP_ID_PREFIX.$level)->json('trx_vip_login_bonus.received_at');

        // 実測: TrxVipLoginBonus は日付の cast を持たない（getDates() が空）ので、
        // ISO 8601 には変換されず、保存する Y-m-d H:i:s（UTC。その日のゲーム日の開始）の文字列のまま返る
        $this->assertSame('2026-10-15 04:00:00', $receivedAt);
    }

    #[Test]
    public function 同じレベルで2回目の受け取りは26001で何も配らない(): void
    {
        $this->login()->assertOk();
        $this->raiseVipLevel();
        $level = $this->currentVipLevel();
        $this->receive(self::VIP_ID_PREFIX.$level, 'req-a')->assertOk();
        $afterFirst = $this->snapshotTables();

        // キー無し・別のキーの二重送信はどちらも ② が偽（受取時のレベルが今のレベルと同じ）
        $this->assertBusinessError($this->receive(self::VIP_ID_PREFIX.$level), GameErrorCode::LOGIN_BONUS_RE_RECEIVE_NOT_AVAILABLE);
        $this->assertBusinessError($this->receive(self::VIP_ID_PREFIX.$level, 'req-b'), GameErrorCode::LOGIN_BONUS_RE_RECEIVE_NOT_AVAILABLE);

        $this->assertSame($afterFirst, $this->snapshotTables(), '2 回目は何も変えない');
    }

    #[Test]
    public function 成功した応答は同じ冪等キーで再送すると保存済みの同じ応答が返り_配らない(): void
    {
        $this->login()->assertOk();
        $this->raiseVipLevel();
        $level = $this->currentVipLevel();
        $first = $this->receive(self::VIP_ID_PREFIX.$level, 'req-same');
        $first->assertOk();
        $afterFirst = $this->snapshotTables();

        $replay = $this->receive(self::VIP_ID_PREFIX.$level, 'req-same');

        $replay->assertOk();
        $replay->assertHeader('X-Idempotency-Cache', 'HIT');
        $this->assertSame($first->json('login_bonus_list'), $replay->json('login_bonus_list'));
        $this->assertSame($afterFirst, $this->snapshotTables());
    }

    #[Test]
    public function 業務エラーも同じ冪等キーでは保存済みの古いエラーが返る(): void
    {
        $this->login()->assertOk();
        $level = $this->currentVipLevel();

        // レベルが上がっていない（② が偽）ので 26001。この応答が 24 時間保存される
        $this->assertBusinessError($this->receive(self::VIP_ID_PREFIX.$level, 'req-err'), GameErrorCode::LOGIN_BONUS_RE_RECEIVE_NOT_AVAILABLE);

        // 状態が変わっても、同じキーなら古い 26001 が返る。新しいキーなら配られる
        $this->raiseVipLevel();
        $level = $this->currentVipLevel();
        $stale = $this->receive(self::VIP_ID_PREFIX.$level, 'req-err');
        $this->assertBusinessError($stale, GameErrorCode::LOGIN_BONUS_RE_RECEIVE_NOT_AVAILABLE);
        $this->receive(self::VIP_ID_PREFIX.$level, 'req-new')->assertOk();
    }

    #[Test]
    public function login前_受け取りの行が無いと26001(): void
    {
        $this->raiseVipLevel();
        $level = $this->currentVipLevel();

        // 初回配布は login の仕事。行が無ければ再受け取りは偽（R7）
        $this->assertBusinessError($this->receive(self::VIP_ID_PREFIX.$level), GameErrorCode::LOGIN_BONUS_RE_RECEIVE_NOT_AVAILABLE);
    }

    #[Test]
    public function 受取日が今日でなければ26001_日またぎ後は再受け取りできない(): void
    {
        $this->login()->assertOk();
        $this->raiseVipLevel();
        $level = $this->currentVipLevel();

        // ゲーム日が変わると、前日の分の差額は受け取れない（login で次の日の分が配られる）
        ClockUtility::setNow(self::DAY2);

        $this->assertBusinessError($this->receive(self::VIP_ID_PREFIX.$level), GameErrorCode::LOGIN_BONUS_RE_RECEIVE_NOT_AVAILABLE);
    }

    #[Test]
    public function レベルが下がった場合も26001(): void
    {
        $this->raiseVipLevel();
        $this->login()->assertOk();
        // 受取時のレベル（高い）より今のレベルを下げる
        DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->update(['vip_point' => 0]);
        $level = $this->currentVipLevel();

        $this->assertBusinessError($this->receive(self::VIP_ID_PREFIX.$level), GameErrorCode::LOGIN_BONUS_RE_RECEIVE_NOT_AVAILABLE);
    }

    #[Test]
    public function 渡した_i_dが今のレベルのものと違うと26002(): void
    {
        $this->login()->assertOk();
        $this->raiseVipLevel();
        $level = $this->currentVipLevel();
        $before = $this->snapshotTables();

        // 旧レベルの ID・存在しない ID はどちらも 26002（課金が先に走って古い ID を送った場合を含む）
        $this->assertBusinessError($this->receive(self::VIP_ID_PREFIX.($level - 1)), GameErrorCode::LOGIN_BONUS_MASTER_NOT_FOUND);
        $this->assertBusinessError($this->receive('no_such_vip_bonus'), GameErrorCode::LOGIN_BONUS_MASTER_NOT_FOUND);
        $this->assertSame($before, $this->snapshotTables());
    }

    #[Test]
    public function 今のレベルの有効な_vi_pマスターが無いと26002(): void
    {
        $this->login()->assertOk();
        $this->raiseVipLevel();
        $level = $this->currentVipLevel();
        DB::connection('mst')->table('mst_vip_login_bonus')->where('vip_level', $level)->update(['is_active' => false]);
        $this->refreshMstCache();

        $this->assertBusinessError($this->receive(self::VIP_ID_PREFIX.$level), GameErrorCode::LOGIN_BONUS_MASTER_NOT_FOUND);
    }

    #[Test]
    public function 据え置いた日数が新レベルのloop_daysを超えると26003(): void
    {
        $this->login()->assertOk();
        $this->raiseVipLevel();
        $level = $this->currentVipLevel();
        // 受け取り状態の日数を、新レベルの loop_days（3）より大きくする
        DB::connection($this->connection)->table('trx_vip_login_bonus')
            ->where('sys_player_id', $this->sysPlayerId)->update(['day' => 4]);
        $before = $this->snapshotTables();

        $this->assertBusinessError($this->receive(self::VIP_ID_PREFIX.$level), GameErrorCode::LOGIN_BONUS_REWARD_NOT_FOUND);
        $this->assertSame($before, $this->snapshotTables(), '配らず、vip_level も更新しない');
    }

    #[Test]
    public function 新レベルのその日の報酬行が無いと26003(): void
    {
        $this->login()->assertOk();
        $this->raiseVipLevel();
        $level = $this->currentVipLevel();
        // 新レベルの 1 日目の報酬行を消す
        DB::connection('mst')->table('mst_vip_login_bonus_content')
            ->where('mst_vip_login_bonus_id', self::VIP_ID_PREFIX.$level)->where('day', 1)->delete();
        $this->refreshMstCache();

        $this->assertBusinessError($this->receive(self::VIP_ID_PREFIX.$level), GameErrorCode::LOGIN_BONUS_REWARD_NOT_FOUND);
    }

    #[Test]
    public function mst_vip_login_bonus_idが無い_長すぎると422(): void
    {
        $headers = $this->authHeaders($this->accessToken);

        $this->postJson('/api/login_bonus/receive', [], $headers)->assertStatus(422);
        $this->postJson('/api/login_bonus/receive', ['mst_vip_login_bonus_id' => str_repeat('a', 65)], $headers)->assertStatus(422);
        $this->postJson('/api/login_bonus/receive', ['mst_vip_login_bonus_id' => 123], $headers)->assertStatus(422);
    }

    #[Test]
    public function 再受け取りの後の同日の2回目のloginでは_is_granted_nowが偽(): void
    {
        $this->login()->assertOk();
        $this->raiseVipLevel();
        $level = $this->currentVipLevel();
        $this->receive(self::VIP_ID_PREFIX.$level)->assertOk();

        $second = $this->login();

        $second->assertOk();
        $this->assertSame([], $second->json('login_bonus_list'));
        $this->assertTrue($second->json('vip_login_bonus_calendar.is_granted_today'));
        $this->assertFalse($second->json('vip_login_bonus_calendar.is_granted_now'));
        $this->assertFalse($second->json('vip_login_bonus_calendar.can_re_receive'));
        $this->assertSame($level, $second->json('vip_login_bonus_calendar.vip_level'));
    }

    #[Test]
    public function 再受け取りは通常の段を変えず_日またぎ後のloginは再受け取りを重ねずに次の日を配る(): void
    {
        $this->login()->assertOk();
        $this->raiseVipLevel();
        $level = $this->currentVipLevel();
        $this->receive(self::VIP_ID_PREFIX.$level)->assertOk();
        $normalLog = DB::connection($this->connection)->table('log_action_login_bonus_receive')
            ->where('sys_player_id', $this->sysPlayerId)->count();

        ClockUtility::setNow(self::DAY2);
        $next = $this->login();

        // 次の日は通常の配布（2 日目）。再受け取りの分は重ならない
        $this->assertSame(2, $next->json('vip_login_bonus_calendar.last_received_day'));
        $this->assertTrue($next->json('vip_login_bonus_calendar.is_granted_now'));
        $this->assertSame($level * 100 + 2, collect($next->json('login_bonus_list'))->firstWhere('id', 'vip_item')['amount']);
        $this->assertGreaterThan($normalLog, DB::connection($this->connection)->table('log_action_login_bonus_receive')
            ->where('sys_player_id', $this->sysPlayerId)->count());
    }

    // ------------------------------------------------------------------
    // 補助
    // ------------------------------------------------------------------

    private function login(): TestResponse
    {
        $this->startFreshRequest();

        return $this->postJson('/api/auth/login', [], $this->authHeaders($this->accessToken));
    }

    private function listCalendar(): TestResponse
    {
        $this->startFreshRequest();

        return $this->postJson('/api/login_bonus/list', [], $this->authHeaders($this->accessToken));
    }

    private function receive(string $mstVipLoginBonusId, ?string $idempotencyKey = null): TestResponse
    {
        $this->startFreshRequest();
        $headers = $this->authHeaders($this->accessToken);
        if ($idempotencyKey !== null) {
            $headers['X-Unique-Request-Identifier'] = $idempotencyKey;
        }

        return $this->postJson('/api/login_bonus/receive', ['mst_vip_login_bonus_id' => $mstVipLoginBonusId], $headers);
    }

    /**
     * 本番ではリクエストごとに新しいプロセスなので、前のリクエストが残したキュー・キャッシュ
     * （書き込みの無い list や、判定で落ちたリクエストが読んだ sys_player など）は無い。テストでもそれに揃える
     */
    private function startFreshRequest(): void
    {
        ApiSession::clearForTest();
        app(QueryManager::class)->clear();
        // 同じプロセスで使い回される Repository のモデルのキャッシュも捨てる（sys_player の古い vip_point を読まないため）
        app(SysPlayerRepository::class)->forgetCachedModels();
    }

    private function assertBusinessError(TestResponse $response, int $errorCode): void
    {
        $response->assertStatus(299);
        $this->assertSame($errorCode, $response->json('error_code'));
    }

    private function currentVipLevel(): int
    {
        $vipPoint = (int) DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->value('vip_point');

        return app(VipLevelService::class)->calculateLevel($vipPoint);
    }

    /**
     * VIP ポイントを大きく足して、レベルを上げる（課金で上がった状態）
     */
    private function raiseVipLevel(): void
    {
        DB::connection('sys')->table('sys_player')->where('id', $this->sysPlayerId)->update(['vip_point' => 1000000]);
    }

    /**
     * 書き込みの有無を比べるための、プレイヤーの trx・log の中身
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function snapshotTables(): array
    {
        $snapshot = [];
        foreach (['trx_login_bonus', 'trx_vip_login_bonus', 'trx_item', 'trx_wallet', 'log_action_vip_login_bonus_receive', 'log_action_login_bonus_receive'] as $table) {
            $snapshot[$table] = DB::connection($this->connection)->table($table)
                ->where('sys_player_id', $this->sysPlayerId)->orderBy('created_at')->get()
                ->map(fn ($row) => (array) $row)->all();
        }

        return $snapshot;
    }

    private function cleanUpMaster(): void
    {
        foreach (['mst_login_bonus_content', 'mst_login_bonus', 'mst_vip_login_bonus_content', 'mst_vip_login_bonus'] as $table) {
            DB::connection('mst')->table($table)->delete();
        }
    }

    /**
     * 通常: 3 日ループ（1 日目は 3 行、2 日目は有償フラグ付き 1 行、3 日目は報酬行なし）。
     * VIP: 全レベルに 3 日ループ。1〜3 日目に vip_item を「レベル × 100 + 日」個
     */
    private function createMaster(): void
    {
        DB::connection('mst')->table('mst_login_bonus')->insert([
            'id' => self::NORMAL_ID, 'type' => 'daily', 'loop_days' => 3, 'is_active' => true,
        ]);
        $rows = [
            // sort_order を同値にして、content_type → content_mst_id の順で並ぶことを見る。
            // わざと並びと逆の順で入れる
            ['day' => 1, 'content_type' => 'item', 'content_mst_id' => 'item_b', 'content_quantity' => 10, 'amount' => 2, 'sort_order' => 1],
            ['day' => 1, 'content_type' => 'item', 'content_mst_id' => 'item_a', 'content_quantity' => 10, 'amount' => 1, 'sort_order' => 1],
            ['day' => 1, 'content_type' => 'wallet', 'content_mst_id' => 'gold', 'content_quantity' => 500, 'amount' => 1, 'sort_order' => 0],
            ['day' => 2, 'content_type' => 'item', 'content_mst_id' => 'free_diamond', 'content_quantity' => 3, 'amount' => 2, 'is_paid' => true, 'sort_order' => 0],
        ];
        // 'gold' は content_type = wallet で type が gold になる。item の sort_order=0 より前に出すため gold を 0、item を 1 にしている
        foreach ($rows as $row) {
            DB::connection('mst')->table('mst_login_bonus_content')->insert([...['mst_login_bonus_id' => self::NORMAL_ID, 'is_paid' => false], ...$row]);
        }

        for ($level = 0; $level <= 20; $level++) {
            $id = self::VIP_ID_PREFIX.$level;
            DB::connection('mst')->table('mst_vip_login_bonus')->insert([
                'id' => $id, 'vip_level' => $level, 'loop_days' => 3, 'is_active' => true,
            ]);
            for ($day = 1; $day <= 3; $day++) {
                DB::connection('mst')->table('mst_vip_login_bonus_content')->insert([
                    'mst_vip_login_bonus_id' => $id, 'day' => $day,
                    'content_type' => 'item', 'content_mst_id' => 'vip_item',
                    'content_quantity' => $level * 100 + $day, 'amount' => 1,
                ]);
            }
        }
    }
}
