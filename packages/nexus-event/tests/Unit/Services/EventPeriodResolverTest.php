<?php

namespace NexusEvent\Tests\Unit\Services;

use NexusEvent\Constants\EventContentType;
use NexusEvent\Contracts\EventMasterRepositoryInterface;
use NexusEvent\DataTransferObjects\Event;
use NexusEvent\DataTransferObjects\EventContent;
use NexusEvent\Services\EventPeriodResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * EventPeriodResolver のテスト
 *
 * - イベントに入っていないコンテンツは null（呼び出し側が自身の期間を使う）
 * - 入っていれば、イベントの期間で上書きする。種別で子の end_at に使う時刻が違う
 * - 同じコンテンツを複数のイベントで使い回したとき、採用するイベントが時刻で決まる
 */
class EventPeriodResolverTest extends TestCase
{
    private FakeEventMasterRepository $repository;

    private EventPeriodResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new FakeEventMasterRepository;
        $this->resolver = new EventPeriodResolver($this->repository);

        // event_a: 11/10〜11/17（end_at）、11/20（exchange_end_at）
        $this->repository->addEvent('event_a', '2026-11-10 10:00:00', '2026-11-17 10:00:00', '2026-11-20 10:00:00');
        // event_b: 12/01〜12/08、12/10。event_a の交換期間の後に始まる
        $this->repository->addEvent('event_b', '2026-12-01 10:00:00', '2026-12-08 10:00:00', '2026-12-10 10:00:00');
    }

    #[Test]
    public function イベントに入っていないコンテンツはnullを返す(): void
    {
        $this->repository->addContent('event_a', EventContentType::GACHA, 'gacha_event');

        $this->assertNull($this->resolver->resolve(EventContentType::GACHA, 'gacha_standard', '2026-11-12 00:00:00'));
        // 同じIDでも、種別が違えば別のコンテンツ
        $this->assertNull($this->resolver->resolve(EventContentType::TRADE, 'gacha_event', '2026-11-12 00:00:00'));
    }

    #[Test]
    public function ガチャはイベントのプレイ期間で上書きする(): void
    {
        $this->repository->addContent('event_a', EventContentType::GACHA, 'gacha_event');

        $period = $this->resolver->resolve(EventContentType::GACHA, 'gacha_event', '2026-11-12 00:00:00');

        $this->assertNotNull($period);
        $this->assertSame('event_a', $period->getMstEventId());
        $this->assertSame('2026-11-10 10:00:00', $period->getStartAt());
        $this->assertSame('2026-11-17 10:00:00', $period->getEndAt());
        $this->assertSame('2026-11-17 10:00:00', $period->getEventEndAt());
        $this->assertSame('2026-11-20 10:00:00', $period->getExchangeEndAt());
    }

    #[Test]
    public function 交換所とミッションは交換期間の終わりまでを子のend_atにする(): void
    {
        $this->repository->addContent('event_a', EventContentType::TRADE, 'trade_event');
        $this->repository->addContent('event_a', EventContentType::MISSION, 'mission_event');

        foreach ([[EventContentType::TRADE, 'trade_event'], [EventContentType::MISSION, 'mission_event']] as [$type, $id]) {
            $period = $this->resolver->resolve($type, $id, '2026-11-12 00:00:00');

            $this->assertNotNull($period, $type);
            $this->assertSame('2026-11-10 10:00:00', $period->getStartAt(), $type);
            $this->assertSame('2026-11-20 10:00:00', $period->getEndAt(), $type);
            // プレイ期間の終わりも取れる（ミッションの進捗の加算は、ここまで）
            $this->assertSame('2026-11-17 10:00:00', $period->getEventEndAt(), $type);
        }
    }

    #[Test]
    public function ログインボーナスとリワードトラックとランキングはプレイ期間の終わりをend_atにする(): void
    {
        $types = [EventContentType::LOGIN_BONUS, EventContentType::REWARD_TRACK, EventContentType::RANKING];

        // マスターは最初の解決で読んでメモリに持つので、先に全て登録する
        foreach ($types as $type) {
            $this->repository->addContent('event_a', $type, 'content_x');
        }

        foreach ($types as $type) {
            $period = $this->resolver->resolve($type, 'content_x', '2026-11-12 00:00:00');

            $this->assertNotNull($period, $type);
            $this->assertSame('2026-11-17 10:00:00', $period->getEndAt(), $type);
        }
    }

    #[Test]
    public function 使い回したコンテンツは開催中のイベントを採用する(): void
    {
        $this->repository->addContent('event_a', EventContentType::GACHA, 'gacha_reuse');
        $this->repository->addContent('event_b', EventContentType::GACHA, 'gacha_reuse');

        $this->assertSame('event_a', $this->resolver->resolve(EventContentType::GACHA, 'gacha_reuse', '2026-11-12 00:00:00')?->getMstEventId());
        // 交換期間中も、そのイベントが開催中の扱い
        $this->assertSame('event_a', $this->resolver->resolve(EventContentType::GACHA, 'gacha_reuse', '2026-11-19 00:00:00')?->getMstEventId());
        $this->assertSame('event_b', $this->resolver->resolve(EventContentType::GACHA, 'gacha_reuse', '2026-12-02 00:00:00')?->getMstEventId());
    }

    #[Test]
    public function 開催中のイベントが無ければ次に始まるイベントを採用する(): void
    {
        $this->repository->addContent('event_a', EventContentType::GACHA, 'gacha_reuse');
        $this->repository->addContent('event_b', EventContentType::GACHA, 'gacha_reuse');

        // event_a の交換期間の後、event_b が始まる前
        $this->assertSame('event_b', $this->resolver->resolve(EventContentType::GACHA, 'gacha_reuse', '2026-11-25 00:00:00')?->getMstEventId());
        // 最初のイベントの前
        $this->assertSame('event_a', $this->resolver->resolve(EventContentType::GACHA, 'gacha_reuse', '2026-11-01 00:00:00')?->getMstEventId());
    }

    #[Test]
    public function 全て終わっていれば直近に終わったイベントを採用する(): void
    {
        $this->repository->addContent('event_a', EventContentType::GACHA, 'gacha_reuse');
        $this->repository->addContent('event_b', EventContentType::GACHA, 'gacha_reuse');

        $this->assertSame('event_b', $this->resolver->resolve(EventContentType::GACHA, 'gacha_reuse', '2027-01-01 00:00:00')?->getMstEventId());
    }

    #[Test]
    public function マスターに無いイベントを指すコンテンツは無視する(): void
    {
        $this->repository->addContent('event_missing', EventContentType::GACHA, 'gacha_orphan');

        $this->assertNull($this->resolver->resolve(EventContentType::GACHA, 'gacha_orphan', '2026-11-12 00:00:00'));
    }

    #[Test]
    public function マスターはリクエストごとに1回だけ読む(): void
    {
        $this->repository->addContent('event_a', EventContentType::GACHA, 'gacha_event');

        $this->resolver->resolve(EventContentType::GACHA, 'gacha_event', '2026-11-12 00:00:00');
        $this->resolver->resolve(EventContentType::GACHA, 'gacha_other', '2026-11-12 00:00:00');
        $this->resolver->resolve(EventContentType::TRADE, 'trade_event', '2026-11-12 00:00:00');

        $this->assertSame(1, $this->repository->selectAllEventsCalls);
    }
}

/**
 * メモリ上のイベントのマスター（テスト用）
 */
class FakeEventMasterRepository implements EventMasterRepositoryInterface
{
    /** @var array<int, Event> */
    public array $events = [];

    /** @var array<int, EventContent> */
    public array $contents = [];

    public int $selectAllEventsCalls = 0;

    public function addEvent(string $id, string $startAt, string $endAt, string $exchangeEndAt): void
    {
        $this->events[] = new Event($id, $startAt, $endAt, $exchangeEndAt, 0);
    }

    public function addContent(string $mstEventId, string $contentType, string $contentMstId): void
    {
        $this->contents[] = new EventContent($mstEventId, $contentType, $contentMstId, 0);
    }

    public function selectAllEvents(): array
    {
        $this->selectAllEventsCalls++;

        return $this->events;
    }

    public function selectAllContents(): array
    {
        return $this->contents;
    }
}
