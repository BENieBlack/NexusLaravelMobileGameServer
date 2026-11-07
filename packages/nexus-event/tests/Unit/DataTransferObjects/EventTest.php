<?php

namespace NexusEvent\Tests\Unit\DataTransferObjects;

use NexusEvent\Constants\EventPhase;
use NexusEvent\DataTransferObjects\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Event の段階の判定
 *
 * start_at ── end_at ── exchange_end_at
 *   開催前 │ 開催中 │ 交換期間中 │ 終了
 * 境界の時刻ちょうどは、手前の段階に含める。
 */
class EventTest extends TestCase
{
    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function phaseProvider(): array
    {
        return [
            '開始の直前は開催前' => ['2026-11-10 09:59:59', EventPhase::BEFORE, false],
            '開始ちょうどは開催中' => ['2026-11-10 10:00:00', EventPhase::PLAYING, true],
            'プレイ期間の終わりちょうどは開催中' => ['2026-11-17 10:00:00', EventPhase::PLAYING, true],
            'プレイ期間の終わりの直後は交換期間中' => ['2026-11-17 10:00:01', EventPhase::EXCHANGING, true],
            '交換期間の終わりちょうどは交換期間中' => ['2026-11-20 10:00:00', EventPhase::EXCHANGING, true],
            '交換期間の終わりの直後は終了' => ['2026-11-20 10:00:01', EventPhase::CLOSED, false],
        ];
    }

    #[Test]
    #[DataProvider('phaseProvider')]
    public function 時刻から段階を判定する(string $now, string $expectedPhase, bool $expectedOpen): void
    {
        $event = new Event('event_a', '2026-11-10 10:00:00', '2026-11-17 10:00:00', '2026-11-20 10:00:00', 0);

        $this->assertSame($expectedPhase, $event->phaseAt($now));
        $this->assertSame($expectedOpen, $event->isOpenAt($now));
    }

    #[Test]
    public function 交換期間が無いイベントは終わりの直後に終了になる(): void
    {
        $event = new Event('event_a', '2026-11-10 10:00:00', '2026-11-17 10:00:00', '2026-11-17 10:00:00', 0);

        $this->assertSame(EventPhase::PLAYING, $event->phaseAt('2026-11-17 10:00:00'));
        $this->assertSame(EventPhase::CLOSED, $event->phaseAt('2026-11-17 10:00:01'));
    }
}
