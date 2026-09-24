<?php

namespace Nexus\Core\Tests\Unit\Utilities;

use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ClockUtility のユニットテスト
 *
 * 差分計算の符号（現在時刻 - 指定日時）と、
 * ゲーム内日付（DAY_START_TIME基準）の判定を確認する。
 */
class ClockUtilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setDayStartTime('00:00:00');
        ClockUtility::setNow('2026-03-15 12:00:00');
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function 過去の日時との差分は正の値になる(): void
    {
        // 経過時間として使われるため、過去は正・未来は負でなければならない
        $this->assertSame(1200, ClockUtility::diffInSeconds('2026-03-15 11:40:00'));
        $this->assertSame(20, ClockUtility::diffInMinutes('2026-03-15 11:40:00'));
        $this->assertSame(3, ClockUtility::diffInHours('2026-03-15 09:00:00'));
        $this->assertSame(3, ClockUtility::diffInDays('2026-03-12 12:00:00'));
    }

    #[Test]
    public function 未来の日時との差分は負の値になる(): void
    {
        $this->assertSame(-600, ClockUtility::diffInSeconds('2026-03-15 12:10:00'));
        $this->assertSame(-10, ClockUtility::diffInMinutes('2026-03-15 12:10:00'));
        $this->assertSame(-2, ClockUtility::diffInHours('2026-03-15 14:00:00'));
        $this->assertSame(-1, ClockUtility::diffInDays('2026-03-16 12:00:00'));
    }

    #[Test]
    public function 同時刻の差分は0になる(): void
    {
        $this->assertSame(0, ClockUtility::diffInSeconds('2026-03-15 12:00:00'));
    }

    #[Test]
    public function 現在時刻を固定して取得できる(): void
    {
        $this->assertSame('2026-03-15 12:00:00', ClockUtility::nowToString());
        $this->assertSame('2026-03-15 12:00:00', ClockUtility::now()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function ゲーム内日付はday_start_timeで区切られる(): void
    {
        ClockUtility::setDayStartTime('09:00:00');

        // 9時前は前日扱い
        $this->assertSame('2026-03-14 09:00:00', ClockUtility::calcGameDayStart('2026-03-15 08:59:59')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-15 09:00:00', ClockUtility::calcGameDayStart('2026-03-15 09:00:00')->format('Y-m-d H:i:s'));

        $this->assertTrue(ClockUtility::isSameGameDay('2026-03-15 10:00:00', '2026-03-16 08:00:00'));
        $this->assertFalse(ClockUtility::isSameGameDay('2026-03-15 08:00:00', '2026-03-15 10:00:00'));
    }

    #[Test]
    public function 今日かどうかはゲーム内日付で判定する(): void
    {
        ClockUtility::setDayStartTime('09:00:00');
        ClockUtility::setNow('2026-03-15 10:00:00');

        $this->assertTrue(ClockUtility::isToday('2026-03-15 23:59:59'));
        $this->assertTrue(ClockUtility::isToday('2026-03-16 08:00:00'));
        $this->assertFalse(ClockUtility::isToday('2026-03-15 08:00:00'));
    }

    #[Test]
    public function 週の年はiso8601基準で返す(): void
    {
        // 2025-12-29(月)〜2026-01-04(日) は 2026-W01
        $this->assertSame(2026, ClockUtility::isoWeekYear('2025-12-29 00:00:00'));
        $this->assertSame(1, ClockUtility::weekOfYear('2025-12-29 00:00:00'));
        $this->assertSame(2025, ClockUtility::year('2025-12-29 00:00:00'));
    }

    #[Test]
    public function 過去と未来を判定できる(): void
    {
        $this->assertTrue(ClockUtility::isPast('2026-03-15 11:59:59'));
        $this->assertFalse(ClockUtility::isPast('2026-03-15 12:00:01'));
        $this->assertFalse(ClockUtility::isPast(null));

        $this->assertTrue(ClockUtility::isFuture('2026-03-15 12:00:01'));
        $this->assertFalse(ClockUtility::isFuture('2026-03-15 11:59:59'));
        $this->assertFalse(ClockUtility::isFuture(null));
    }

    #[Test]
    public function 期間内かどうかを判定できる(): void
    {
        $this->assertTrue(ClockUtility::isWithin('2026-03-15 11:00:00', '2026-03-15 13:00:00'));
        $this->assertFalse(ClockUtility::isWithin('2026-03-15 13:00:00', '2026-03-15 14:00:00'));
        $this->assertFalse(ClockUtility::isWithin('2026-03-15 10:00:00', '2026-03-15 11:00:00'));

        // 開始・終了が未指定なら片側だけで判定する
        $this->assertTrue(ClockUtility::isWithin(null, '2026-03-15 13:00:00'));
        $this->assertTrue(ClockUtility::isWithin('2026-03-15 11:00:00', null));
        $this->assertTrue(ClockUtility::isWithin(null, null));
    }
}
