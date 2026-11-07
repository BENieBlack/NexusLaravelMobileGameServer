<?php

namespace Tests\Feature\Event;

use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusEvent\Constants\EventContentType;
use NexusEvent\Constants\EventPhase;
use NexusEvent\Services\EventPeriodResolver;
use NexusEvent\Services\EventService;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * マスターのDB（mst_event / mst_event_content）から、EventPeriodResolver / EventService が動くことの確認
 *
 * Model → DTO の変換（日時の形式、既定値）と、コンテナの束縛（Adapter）を通して確かめる。
 */
class EventPeriodResolverTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const EVENT_IDS = ['event_test_a', 'event_test_b'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUpMaster();

        // event_a: 11/10〜11/17（end_at）、11/20（exchange_end_at）。event_b は12月で、期間は重ならない
        DB::connection('mst')->table('mst_event')->insert([
            ['id' => 'event_test_a', 'start_at' => '2026-11-10 10:00:00', 'end_at' => '2026-11-17 10:00:00', 'exchange_end_at' => '2026-11-20 10:00:00', 'sort_order' => 1],
            ['id' => 'event_test_b', 'start_at' => '2026-12-01 10:00:00', 'end_at' => '2026-12-08 10:00:00', 'exchange_end_at' => '2026-12-10 10:00:00', 'sort_order' => 2],
        ]);
        DB::connection('mst')->table('mst_event_content')->insert([
            ['mst_event_id' => 'event_test_a', 'content_type' => EventContentType::GACHA, 'content_mst_id' => 'gacha_test_reuse', 'sort_order' => 2],
            ['mst_event_id' => 'event_test_b', 'content_type' => EventContentType::GACHA, 'content_mst_id' => 'gacha_test_reuse', 'sort_order' => 1],
            ['mst_event_id' => 'event_test_a', 'content_type' => EventContentType::TRADE, 'content_mst_id' => 'trade_test_only_a', 'sort_order' => 1],
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
    public function マスターの_d_bからコンテンツの期間を上書きして返す(): void
    {
        ClockUtility::setNow('2026-11-12 00:00:00');

        $period = app(EventPeriodResolver::class)->resolve(EventContentType::TRADE, 'trade_test_only_a');

        $this->assertNotNull($period);
        $this->assertSame('event_test_a', $period->getMstEventId());
        $this->assertSame('2026-11-10 10:00:00', $period->getStartAt());
        // 交換所は交換期間の終わりまで
        $this->assertSame('2026-11-20 10:00:00', $period->getEndAt());
        $this->assertSame('2026-11-17 10:00:00', $period->getEventEndAt());
    }

    #[Test]
    public function イベントに入っていないコンテンツはnullを返す(): void
    {
        ClockUtility::setNow('2026-11-12 00:00:00');

        $this->assertNull(app(EventPeriodResolver::class)->resolve(EventContentType::GACHA, 'gacha_test_standard'));
    }

    #[Test]
    public function 使い回したコンテンツは現在時刻で採用するイベントが変わる(): void
    {
        ClockUtility::setNow('2026-11-12 00:00:00');
        $this->assertSame('event_test_a', app(EventPeriodResolver::class)->resolve(EventContentType::GACHA, 'gacha_test_reuse')?->getMstEventId());

        // リゾルバーはリクエストごとにマスターを持つ。時刻を進めたら、新しいリクエストとして作り直す
        app()->forgetInstance(EventPeriodResolver::class);
        ClockUtility::setNow('2026-12-02 00:00:00');
        $this->assertSame('event_test_b', app(EventPeriodResolver::class)->resolve(EventContentType::GACHA, 'gacha_test_reuse')?->getMstEventId());
    }

    #[Test]
    public function 開催中のイベントと段階を取得できる(): void
    {
        $service = app(EventService::class);

        ClockUtility::setNow('2026-11-18 00:00:00');
        $current = $service->findCurrentEvent();
        $this->assertSame('event_test_a', $current?->getId());
        $this->assertSame(EventPhase::EXCHANGING, $current?->phaseAt());

        // event_a の交換期間の後、event_b の前は、開催中のイベントが無い
        ClockUtility::setNow('2026-11-25 00:00:00');
        $this->assertNull($service->findCurrentEvent());
    }

    #[Test]
    public function イベントに束ねたコンテンツを表示順で取得できる(): void
    {
        $contents = app(EventService::class)->selectContentsByEventId('event_test_a');

        $this->assertSame(['trade_test_only_a', 'gacha_test_reuse'], array_map(fn ($c) => $c->getContentMstId(), $contents));
    }

    private function cleanUpMaster(): void
    {
        DB::connection('mst')->table('mst_event_content')->whereIn('mst_event_id', self::EVENT_IDS)->delete();
        DB::connection('mst')->table('mst_event__l10n')->whereIn('mst_event_id', self::EVENT_IDS)->delete();
        DB::connection('mst')->table('mst_event')->whereIn('id', self::EVENT_IDS)->delete();
    }
}
