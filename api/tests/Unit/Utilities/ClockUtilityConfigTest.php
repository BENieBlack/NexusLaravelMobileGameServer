<?php

namespace Tests\Unit\Utilities;

use Illuminate\Support\Facades\Config;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ClockUtility の DAY_START_TIME 解決のテスト
 *
 * env() を直接読むと config:cache 済みの本番で null が返り、
 * 日跨ぎ判定（ログインボーナス等）が壊れる。
 * config 経由で解決していることをここで固定する。
 */
class ClockUtilityConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::reset();
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function 設定ファイルからday_start_timeを読む(): void
    {
        Config::set('nexus-core.day_start_time', '09:00:00');

        $this->assertSame('09:00:00', ClockUtility::calcDayStartTime());
    }

    #[Test]
    public function 設定が無ければ0時を既定にする(): void
    {
        Config::set('nexus-core.day_start_time', null);

        $this->assertSame('00:00:00', ClockUtility::calcDayStartTime());
    }

    #[Test]
    public function パッケージの設定がマージされている(): void
    {
        // ServiceProviderのmergeConfigFromでキーが生える。
        // 値は env(DAY_START_TIME) 由来なので運用で変わる。
        // ここで '00:00:00' を決め打ちすると、境界を変えた瞬間に落ちる
        $dayStartTime = config('nexus-core.day_start_time');

        $this->assertNotNull($dayStartTime, 'mergeConfigFromでキーが入っていない');
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', (string) $dayStartTime);
    }

    #[Test]
    public function 読んだ値はキャッシュされる(): void
    {
        Config::set('nexus-core.day_start_time', '05:00:00');
        $this->assertSame('05:00:00', ClockUtility::calcDayStartTime());

        // 2回目以降はconfigを引き直さない
        Config::set('nexus-core.day_start_time', '23:00:00');
        $this->assertSame('05:00:00', ClockUtility::calcDayStartTime());
    }
}
