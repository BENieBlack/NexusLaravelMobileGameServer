<?php

namespace Tests\Feature\Mission;

use App\Exceptions\GameErrorCode;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Nexus\Core\UnitOfWork\Persistence\QueryManager;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * ミッションのテスト
 *
 * ユニットのレベルアップが、ユニットに付いたタグごとのカウンタ（trx_progress）を進め、
 * ミッションはそのカウンタを参照して達成・受け取りできることが要点。
 * 1回のレベルアップで、ユニットが持つ複数のタグのミッションが同時に進む。
 */
class MissionEndpointTest extends TestCase
{
    use RefreshMultipleDatabases;

    /** Vガンダム系 かつ ガンダムタイプ のユニット */
    private const UNIT_ID = 'unit_mission_v_gundam';

    private const EXP_ITEM_ID = 'item_mission_unit_exp';

    private const REWARD_ITEM_ID = 'item_mission_reward';

    private const MISSION_IDS = ['mission_v_gundam_lv', 'mission_gundam_type_lv', 'mission_wing_lv', 'mission_any_lv', 'mission_ended'];

    private int $sysPlayerId;

    private string $accessToken;

    private string $connection;

    private int $trxUnitId;

    public function beginDatabaseTransaction(): void
    {
        // UseCaseが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-10-15 12:00:00');

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
        foreach (['trx_progress', 'trx_mission', 'trx_unit', 'trx_item'] as $table) {
            DB::connection($this->connection)->table($table)
                ->where('sys_player_id', $this->sysPlayerId)->delete();
        }

        $this->cleanUpMaster();
        // マスターキャッシュに自分が入れた行が残ったままだと、後続のテストが
        // 自前で入れたマスターを引けなくなる
        $this->refreshMstCache();
        ApiSession::clearForTest();
        ClockUtility::reset();
        app(QueryManager::class)->clear();

        parent::tearDown();
    }

    // =========================================================
    // カウンタ
    // =========================================================

    #[Test]
    public function ユニットのレベルアップでタグごとと通算のカウンタが上がったレベル分進む(): void
    {
        // level 1（累積0）に 100exp × 3 = 300exp → level 3（250）。2レベル上がる
        $this->levelUp(useCount: 3);

        $this->assertSame(
            ['all' => 2, 'tag_series_v_gundam' => 2, 'tag_type_gundam' => 2],
            $this->findProgressValues('unit_level_up'),
        );
    }

    #[Test]
    public function レベルが上がらなければカウンタは進まない(): void
    {
        // 50exp では level 2（100）に届かない
        $this->levelUp(useCount: 0, exp: 50);

        $this->assertSame([], $this->findProgressValues('unit_level_up'));
    }

    // =========================================================
    // 一覧
    // =========================================================

    #[Test]
    public function 一覧はユニットのタグに合うミッションだけ進んで見える(): void
    {
        $this->levelUp(useCount: 3);

        $response = $this->withHeaders($this->authHeaders($this->accessToken))
            ->getJson('/api/mission/list');

        $response->assertOk();
        $missionById = collect($response->json('mission_list'))->keyBy('mst_mission_id');

        $this->assertSame(
            ['mission_v_gundam_lv', 'mission_gundam_type_lv', 'mission_wing_lv', 'mission_any_lv'],
            $missionById->keys()->all(),
            '期間の終わったミッションは出さず、表示順に並ぶ',
        );

        // 1回のレベルアップで、Vガンダム系 と ガンダムタイプ の両方が進む
        $this->assertSame(2, $missionById['mission_v_gundam_lv']['progress_value']);
        $this->assertTrue($missionById['mission_v_gundam_lv']['is_completed']);
        $this->assertSame(2, $missionById['mission_gundam_type_lv']['progress_value']);
        $this->assertFalse($missionById['mission_gundam_type_lv']['is_completed']);

        // タグを持たない作品のミッションは進まない
        $this->assertSame(0, $missionById['mission_wing_lv']['progress_value']);

        // タグ指定の無いミッションは通算を見る
        $this->assertSame(2, $missionById['mission_any_lv']['progress_value']);

        $this->assertSame(
            [['content_type' => 'item', 'content_mst_id' => self::REWARD_ITEM_ID, 'content_quantity' => 5]],
            $missionById['mission_v_gundam_lv']['content_list'],
        );
    }

    // =========================================================
    // 受け取り
    // =========================================================

    #[Test]
    public function 達成したミッションの報酬を受け取れる(): void
    {
        $this->levelUp(useCount: 3);

        $response = $this->receive('mission_v_gundam_lv');

        $response->assertOk();
        $response->assertJsonPath('mission.mst_mission_id', 'mission_v_gundam_lv');
        $response->assertJsonPath('mission.is_received', true);
        $response->assertJsonPath('mission.received_at', '2026-10-15 12:00:00');

        $this->assertSame(5, $this->findItemAmount(self::REWARD_ITEM_ID), '報酬のアイテムが配られる');
        $this->assertTrue(
            DB::connection($this->connection)->table('trx_mission')
                ->where('sys_player_id', $this->sysPlayerId)
                ->where('mst_mission_id', 'mission_v_gundam_lv')
                ->exists(),
            '受け取り記録が残る',
        );
        $this->assertSame(
            2,
            $this->findProgressValues('unit_level_up')['tag_series_v_gundam'],
            'カウンタは他のミッションと共有なので受け取っても減らない',
        );
    }

    #[Test]
    public function 受け取り済みのミッションは二度受け取れない(): void
    {
        $this->levelUp(useCount: 3);
        $this->receive('mission_v_gundam_lv')->assertOk();

        $response = $this->receive('mission_v_gundam_lv');

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::MISSION_ALREADY_RECEIVED, $response->json('error_code'));
        $this->assertSame(5, $this->findItemAmount(self::REWARD_ITEM_ID), '報酬は1回分だけ');
    }

    #[Test]
    public function 未達成のミッションは受け取れない(): void
    {
        $this->levelUp(useCount: 3);

        $response = $this->receive('mission_gundam_type_lv');

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::MISSION_NOT_COMPLETED, $response->json('error_code'));
    }

    #[Test]
    public function 期間の終わったミッションは受け取れない(): void
    {
        $this->levelUp(useCount: 3);

        $response = $this->receive('mission_ended');

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::MISSION_NOT_OPEN, $response->json('error_code'));
    }

    #[Test]
    public function 存在しないミッションは受け取れない(): void
    {
        $response = $this->receive('mission_none');

        $response->assertStatus(299);
        $this->assertSame(GameErrorCode::MISSION_NOT_FOUND, $response->json('error_code'));
    }

    #[Test]
    public function ミッション_i_dが無ければ受け取れない(): void
    {
        $this->withHeaders($this->authHeaders($this->accessToken))
            ->postJson('/api/mission/receive', [])
            ->assertStatus(422);
    }

    #[Test]
    public function 認証が無ければ取得できない(): void
    {
        $this->getJson('/api/mission/list')->assertStatus(401);
    }

    private function levelUp(int $useCount, int $exp = 0): void
    {
        if ($exp > 0) {
            // アイテムを使わずに経験値だけ足したいときは、アイテムの効果量を変える
            DB::connection('mst')->table('mst_item')->where('id', self::EXP_ITEM_ID)->update(['value' => $exp]);
            $this->refreshMstCache();
            $useCount = 1;
        }

        $this->withHeaders($this->authHeaders($this->accessToken))
            ->postJson('/api/unit/level_up', [
                'trx_unit_id' => $this->trxUnitId,
                'mst_item_id' => self::EXP_ITEM_ID,
                'use_count' => $useCount,
            ])
            ->assertOk();
    }

    private function receive(string $mstMissionId): TestResponse
    {
        return $this->withHeaders($this->authHeaders($this->accessToken))
            ->postJson('/api/mission/receive', ['mst_mission_id' => $mstMissionId]);
    }

    /**
     * 出来事のカウンタを sub_target_id => 値 で返す（キー順）
     *
     * @return array<string, int>
     */
    private function findProgressValues(string $triggerType): array
    {
        return DB::connection($this->connection)->table('trx_progress')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('trigger_type', $triggerType)
            ->orderBy('sub_target_id')
            ->pluck('progress_value', 'sub_target_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function findItemAmount(string $mstItemId): int
    {
        $row = DB::connection($this->connection)->table('trx_item')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        return $row ? (int) $row->free_amount + (int) $row->paid_amount : 0;
    }

    private function createPlayerData(): void
    {
        $this->trxUnitId = DB::connection($this->connection)->table('trx_unit')->insertGetId([
            'sys_player_id' => $this->sysPlayerId,
            'mst_unit_id' => self::UNIT_ID,
            'grade' => 1,
            'level' => 1,
            'level_exp' => 0,
            'is_delete' => false,
        ]);

        DB::connection($this->connection)->table('trx_item')->insert([
            'sys_player_id' => $this->sysPlayerId,
            'mst_item_id' => self::EXP_ITEM_ID,
            'free_amount' => 100,
            'paid_amount' => 0,
            'is_delete' => false,
        ]);
    }

    private function createMaster(): void
    {
        DB::connection('mst')->table('mst_unit')->insert([
            'id' => self::UNIT_ID, 'type' => 'attack', 'element' => 'light', 'rarity' => 'SSR',
        ]);

        DB::connection('mst')->table('mst_unit_level')->insert([
            ['rarity' => 'SSR', 'level' => 1, 'required_exp' => 0],
            ['rarity' => 'SSR', 'level' => 2, 'required_exp' => 100],
            ['rarity' => 'SSR', 'level' => 3, 'required_exp' => 250],
            ['rarity' => 'SSR', 'level' => 4, 'required_exp' => 1000],
        ]);

        DB::connection('mst')->table('mst_item')->insert([
            ['id' => self::EXP_ITEM_ID, 'type' => 'material', 'effect' => 'unit_exp', 'value' => 100],
            ['id' => self::REWARD_ITEM_ID, 'type' => 'material', 'effect' => 'none', 'value' => 0],
        ]);

        DB::connection('mst')->table('mst_tag')->insert([
            ['id' => 'tag_series_v_gundam', 'tag_category' => 'series', 'sort_order' => 1],
            ['id' => 'tag_series_wing', 'tag_category' => 'series', 'sort_order' => 2],
            ['id' => 'tag_type_gundam', 'tag_category' => 'chara_type', 'sort_order' => 3],
        ]);

        DB::connection('mst')->table('mst_tag_content')->insert([
            ['mst_tag_id' => 'tag_series_v_gundam', 'content_type' => 'unit', 'content_mst_id' => self::UNIT_ID],
            ['mst_tag_id' => 'tag_type_gundam', 'content_type' => 'unit', 'content_mst_id' => self::UNIT_ID],
        ]);

        $mission = fn (string $id, ?string $mstTagId, int $targetValue, int $sortOrder, ?string $endAt = null) => [
            'id' => $id,
            'trigger_type' => 'unit_level_up',
            'mst_tag_id' => $mstTagId,
            'target_value' => $targetValue,
            'start_at' => '2026-10-01 00:00:00',
            'end_at' => $endAt,
            'sort_order' => $sortOrder,
        ];
        DB::connection('mst')->table('mst_mission')->insert([
            $mission('mission_v_gundam_lv', 'tag_series_v_gundam', 2, 1),
            $mission('mission_gundam_type_lv', 'tag_type_gundam', 5, 2),
            $mission('mission_wing_lv', 'tag_series_wing', 1, 3),
            $mission('mission_any_lv', null, 10, 4),
            $mission('mission_ended', 'tag_series_v_gundam', 1, 5, '2026-10-14 23:59:59'),
        ]);

        DB::connection('mst')->table('mst_mission_content')->insert([
            'mst_mission_id' => 'mission_v_gundam_lv',
            'content_type' => 'item',
            'content_mst_id' => self::REWARD_ITEM_ID,
            'content_quantity' => 5,
            'sort_order' => 1,
        ]);
    }

    private function cleanUpMaster(): void
    {
        DB::connection('mst')->table('mst_mission_content')->whereIn('mst_mission_id', self::MISSION_IDS)->delete();
        DB::connection('mst')->table('mst_mission')->whereIn('id', self::MISSION_IDS)->delete();
        DB::connection('mst')->table('mst_tag_content')->where('content_mst_id', self::UNIT_ID)->delete();
        DB::connection('mst')->table('mst_tag')
            ->whereIn('id', ['tag_series_v_gundam', 'tag_series_wing', 'tag_type_gundam'])->delete();
        DB::connection('mst')->table('mst_item')->whereIn('id', [self::EXP_ITEM_ID, self::REWARD_ITEM_ID])->delete();
        DB::connection('mst')->table('mst_unit_level')->where('rarity', 'SSR')->delete();
        DB::connection('mst')->table('mst_unit')->where('id', self::UNIT_ID)->delete();
    }
}
