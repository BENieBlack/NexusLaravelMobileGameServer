<?php

namespace Tests\Feature\Event;

use App\Domain\Gacha\Services\GachaValidationService;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Mst\MstGacha;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusEvent\Constants\EventContentType;
use NexusEvent\Services\EventPeriodResolver;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;
use NexusMission\Contracts\MissionMasterRepositoryInterface;
use NexusRewardTrack\Contracts\RewardTrackMasterRepositoryInterface;
use NexusTrade\Contracts\TradeMasterRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * イベントに入っているコンテンツは、自身の期間ではなくイベントの期間で判定されることの確認
 *
 * ガチャ・ミッション・交換所（商品を含む）・リワードトラック・ログインボーナスの、マスター読み取りを通す。
 * イベントに入っていないコンテンツは、今までどおり自身の期間で判定される。
 *
 * event_a: 11/10 10:00 〜 11/17 10:00（end_at）、11/20 10:00（exchange_end_at）
 * どのコンテンツも、自身の期間は 2026-01 に終わっていて、イベントに入っているときだけ使える。
 */
class EventPeriodOverrideTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const EVENT_ID = 'evtest_event_a';

    private const OLD_START = '2026-01-01 00:00:00';

    private const OLD_END = '2026-01-31 00:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUpMaster();

        DB::connection('mst')->table('mst_event')->insert([
            'id' => self::EVENT_ID,
            'start_at' => '2026-11-10 10:00:00',
            'end_at' => '2026-11-17 10:00:00',
            'exchange_end_at' => '2026-11-20 10:00:00',
        ]);

        $this->refreshMstCache();
    }

    protected function tearDown(): void
    {
        $this->cleanUpMaster();
        $this->refreshMstCache();

        parent::tearDown();
    }

    #[Test]
    public function ガチャはイベントのプレイ期間で判定する(): void
    {
        $this->addEventContent(EventContentType::GACHA, 'evtest_gacha_in_event');
        $this->refreshMstCache();

        $inEvent = $this->makeGacha('evtest_gacha_in_event', self::OLD_START, self::OLD_END);
        $service = app(GachaValidationService::class);

        // 自身の期間は終わっているが、イベントのプレイ期間中なので引ける
        ClockUtility::setNow('2026-11-12 00:00:00');
        $service->validateGachaPeriod($inEvent);
        $this->addToAssertionCount(1);

        // 自身の期間は開いていても、イベントのプレイ期間の後は引けない（交換期間中でも引けない）
        $openOwnPeriod = $this->makeGacha('evtest_gacha_in_event', '2026-11-01 00:00:00', '2026-12-31 00:00:00');
        ClockUtility::setNow('2026-11-18 00:00:00');

        try {
            $service->validateGachaPeriod($openOwnPeriod);
            $this->fail('イベントのプレイ期間の後は、ガチャを引けないはず');
        } catch (GameException $e) {
            $this->assertSame(GameErrorCode::GACHA_NOT_AVAILABLE, $e->getErrorCode());
        }
    }

    #[Test]
    public function イベントに入っていないガチャは自身の期間で判定する(): void
    {
        $standalone = $this->makeGacha('evtest_gacha_standalone', self::OLD_START, self::OLD_END);

        ClockUtility::setNow('2026-11-12 00:00:00');

        try {
            app(GachaValidationService::class)->validateGachaPeriod($standalone);
            $this->fail('自身の期間が終わっているので、引けないはず');
        } catch (GameException $e) {
            $this->assertSame(GameErrorCode::GACHA_NOT_AVAILABLE, $e->getErrorCode());
        }
    }

    #[Test]
    public function ミッションはイベントの開始から交換期間の終わりまでにする(): void
    {
        $this->insertMission('evtest_mission_in_event');
        $this->insertMission('evtest_mission_standalone');
        $this->addEventContent(EventContentType::MISSION, 'evtest_mission_in_event');
        $this->refreshMstCache();

        $repository = app(MissionMasterRepositoryInterface::class);

        $inEvent = $repository->selectMissionById('evtest_mission_in_event');
        $this->assertSame('2026-11-10 10:00:00', $inEvent?->getStartAt());
        // 報酬の受け取りは交換期間まで
        $this->assertSame('2026-11-20 10:00:00', $inEvent?->getEndAt());

        $standalone = $repository->selectMissionById('evtest_mission_standalone');
        $this->assertSame(self::OLD_START, $standalone?->getStartAt());
        $this->assertSame(self::OLD_END, $standalone?->getEndAt());

        $all = collect($repository->selectAllMissions())->keyBy(fn ($mission) => $mission->getId());
        $this->assertSame('2026-11-20 10:00:00', $all['evtest_mission_in_event']->getEndAt());
        $this->assertSame(self::OLD_END, $all['evtest_mission_standalone']->getEndAt());
    }

    #[Test]
    public function 交換所と商品はイベントの開始から交換期間の終わりまでにする(): void
    {
        DB::connection('mst')->table('mst_trade')->insert([
            ['id' => 'evtest_trade_in_event', 'start_at' => self::OLD_START, 'end_at' => self::OLD_END],
            ['id' => 'evtest_trade_standalone', 'start_at' => self::OLD_START, 'end_at' => self::OLD_END],
        ]);
        DB::connection('mst')->table('mst_trade_product')->insert([
            $this->tradeProductRow('evtest_product_in_event', 'evtest_trade_in_event'),
            $this->tradeProductRow('evtest_product_standalone', 'evtest_trade_standalone'),
        ]);
        $this->addEventContent(EventContentType::TRADE, 'evtest_trade_in_event');
        $this->refreshMstCache();

        $repository = app(TradeMasterRepositoryInterface::class);

        $trade = $repository->selectTradeById('evtest_trade_in_event');
        $this->assertSame('2026-11-10 10:00:00', $trade?->getStartAt());
        $this->assertSame('2026-11-20 10:00:00', $trade?->getEndAt());

        // 交換所を使い回したとき、商品に古い期間が残らないよう、商品も同じイベントの期間にする
        $product = $repository->selectProductById('evtest_product_in_event');
        $this->assertSame('2026-11-10 10:00:00', $product?->getStartAt());
        $this->assertSame('2026-11-20 10:00:00', $product?->getEndAt());

        $standaloneProduct = $repository->selectProductById('evtest_product_standalone');
        $this->assertSame(self::OLD_START, $standaloneProduct?->getStartAt());
        $this->assertSame(self::OLD_END, $standaloneProduct?->getEndAt());
        $this->assertSame(self::OLD_END, $repository->selectTradeById('evtest_trade_standalone')?->getEndAt());
    }

    #[Test]
    public function リワードトラックはイベントの期間で開催中かを判定する(): void
    {
        foreach (['evtest_track_in_event', 'evtest_track_standalone'] as $id) {
            DB::connection('mst')->table('mst_reward_track')->insert([
                'id' => $id, 'progress_type' => 'login_days', 'start_at' => self::OLD_START, 'end_at' => self::OLD_END,
            ]);
        }
        $this->addEventContent(EventContentType::REWARD_TRACK, 'evtest_track_in_event');
        $this->refreshMstCache();

        $repository = app(RewardTrackMasterRepositoryInterface::class);

        // イベントの期間中は、自身の期間が終わっていても開催中。入っていないトラックは自身の期間のまま
        ClockUtility::setNow('2026-11-12 00:00:00');
        $activeIds = array_column($repository->selectActiveTracks(), 'id');
        $this->assertContains('evtest_track_in_event', $activeIds);
        $this->assertNotContains('evtest_track_standalone', $activeIds);

        $track = $repository->selectTrackById('evtest_track_in_event');
        $this->assertSame('2026-11-10 10:00:00', $track['start_at']);
        $this->assertSame('2026-11-17 10:00:00', $track['end_at']);

        // イベントのプレイ期間の後は、開催中ではない
        ClockUtility::setNow('2026-11-18 00:00:00');
        $this->assertNotContains('evtest_track_in_event', array_column($repository->selectActiveTracks(), 'id'));
    }

    #[Test]
    public function カムバックボーナスはイベントの期間で判定する(): void
    {
        foreach (['evtest_comeback_in_event', 'evtest_comeback_standalone'] as $id) {
            DB::connection('mst')->table('mst_login_bonus')->insert($this->loginBonusRow($id, 'comeback'));
        }
        $this->addEventContent(EventContentType::LOGIN_BONUS, 'evtest_comeback_in_event');
        $this->refreshMstCache();

        $repository = app(LoginBonusRepositoryInterface::class);

        ClockUtility::setNow('2026-11-12 00:00:00');
        $this->assertSame('evtest_comeback_in_event', $repository->selectActiveComebackBonusById('evtest_comeback_in_event')['id'] ?? null);
        $this->assertNull($repository->selectActiveComebackBonusById('evtest_comeback_standalone'));

        // イベントのプレイ期間の後は、使えない
        ClockUtility::setNow('2026-11-18 00:00:00');
        $this->assertNull($repository->selectActiveComebackBonusById('evtest_comeback_in_event'));
    }

    #[Test]
    public function 通常ログインボーナスはイベントに入っているときだけ期間を見る(): void
    {
        DB::connection('mst')->table('mst_login_bonus')->insert($this->loginBonusRow('evtest_daily_in_event', 'daily'));
        $this->addEventContent(EventContentType::LOGIN_BONUS, 'evtest_daily_in_event');
        $this->refreshMstCache();

        $repository = app(LoginBonusRepositoryInterface::class);

        ClockUtility::setNow('2026-11-12 00:00:00');
        $this->assertSame('evtest_daily_in_event', $repository->selectActiveDailyBonus()['id'] ?? null);

        // イベントのプレイ期間の後は、無いものとして扱う
        ClockUtility::setNow('2026-11-18 00:00:00');
        $this->assertNull($repository->selectActiveDailyBonus());
        $this->assertNull($repository->selectLoopDaysForActiveBonus());

        // イベントに入っていなければ、従来どおり期間を見ない（自身の期間が終わっていても有効）
        DB::connection('mst')->table('mst_event_content')->where('content_mst_id', 'evtest_daily_in_event')->delete();
        $this->refreshMstCache();
        app()->forgetInstance(EventPeriodResolver::class);
        $this->assertSame('evtest_daily_in_event', app(LoginBonusRepositoryInterface::class)->selectActiveDailyBonus()['id'] ?? null);
    }

    private function makeGacha(string $id, string $startAt, string $endAt): MstGacha
    {
        return (new MstGacha)->forceFill(['id' => $id, 'start_at' => $startAt, 'end_at' => $endAt]);
    }

    private function addEventContent(string $contentType, string $contentMstId): void
    {
        DB::connection('mst')->table('mst_event_content')->insert([
            'mst_event_id' => self::EVENT_ID,
            'content_type' => $contentType,
            'content_mst_id' => $contentMstId,
        ]);
    }

    private function insertMission(string $id): void
    {
        DB::connection('mst')->table('mst_mission')->insert([
            'id' => $id, 'trigger_type' => 'unit_level_up', 'target_value' => 3,
            'start_at' => self::OLD_START, 'end_at' => self::OLD_END,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tradeProductRow(string $id, string $mstTradeId): array
    {
        return [
            'id' => $id, 'mst_trade_id' => $mstTradeId, 'cost_type' => 'diamond', 'cost_mst_id' => null,
            'cost_amount' => 10, 'trade_limit' => null, 'reset_type' => 'none',
            'start_at' => self::OLD_START, 'end_at' => self::OLD_END,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loginBonusRow(string $id, string $type): array
    {
        return [
            'id' => $id, 'type' => $type, 'loop_days' => 7, 'required_absent_days' => 0, 'valid_days' => 7,
            'priority' => 1, 'is_active' => true, 'start_at' => self::OLD_START, 'end_at' => self::OLD_END,
        ];
    }

    private function cleanUpMaster(): void
    {
        $mst = DB::connection('mst');
        $mst->table('mst_event_content')->where('mst_event_id', self::EVENT_ID)->delete();
        $mst->table('mst_event')->where('id', self::EVENT_ID)->delete();
        $mst->table('mst_mission')->where('id', 'like', 'evtest_%')->delete();
        $mst->table('mst_trade_product')->where('id', 'like', 'evtest_%')->delete();
        $mst->table('mst_trade')->where('id', 'like', 'evtest_%')->delete();
        $mst->table('mst_reward_track')->where('id', 'like', 'evtest_%')->delete();
        $mst->table('mst_login_bonus')->where('id', 'like', 'evtest_%')->delete();
    }
}
