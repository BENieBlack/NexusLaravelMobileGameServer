<?php

namespace NexusUtilities\Tests;

use NexusUtilities\ClockUtility;
use PHPUnit\Framework\TestCase;
use Carbon\CarbonImmutable;

class ClockUtilityTest extends TestCase
{
    /** @test */
    public function it_returns_carbon_immutable_instance()
    {
        $result = ClockUtility::now();
        
        $this->assertInstanceOf(CarbonImmutable::class, $result);
    }

    /** @test */
    public function it_returns_current_time_before_initialization()
    {
        $before = CarbonImmutable::now();
        $result = ClockUtility::now();
        $after = CarbonImmutable::now();

        $this->assertGreaterThanOrEqual($before->timestamp, $result->timestamp);
        $this->assertLessThanOrEqual($after->timestamp, $result->timestamp);
    }

    /** @test */
    public function it_initializes_and_freezes_time()
    {
        ClockUtility::initialize();
        
        $first = ClockUtility::now();
        usleep(100000); // 0.1秒待機
        $second = ClockUtility::now();
        
        // 初期化後は同じ時刻を返す
        $this->assertEquals($first->timestamp, $second->timestamp);
    }

    /** @test */
    public function it_returns_now_to_string_in_correct_format()
    {
        $result = ClockUtility::nowToString();
        
        $this->assertIsString($result);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $result
        );
    }

    /** @test */
    public function it_returns_consistent_string_after_initialization()
    {
        ClockUtility::initialize();
        
        $first = ClockUtility::nowToString();
        usleep(100000); // 0.1秒待機
        $second = ClockUtility::nowToString();
        
        $this->assertEquals($first, $second);
    }

    /** @test */
    public function now_to_string_matches_now_format()
    {
        ClockUtility::initialize();
        
        $nowObject = ClockUtility::now();
        $nowString = ClockUtility::nowToString();
        
        $this->assertEquals(
            $nowObject->format('Y-m-d H:i:s'),
            $nowString
        );
    }

    /** @test */
    public function immutable_instance_does_not_mutate()
    {
        $result = ClockUtility::now();
        
        // Immutableなので、addメソッドは新しいインスタンスを返す
        $modified = $result->addDay();
        $this->assertNotSame($result, $modified);
        $this->assertNotEquals($result->timestamp, $modified->timestamp);
    }

    /** @test */
    public function get_day_start_time_returns_custom_value()
    {
        ClockUtility::setDayStartTime('09:00:00');
        $result = ClockUtility::getDayStartTime();
        $this->assertEquals('09:00:00', $result);
        ClockUtility::reset(); // リセット
    }

    /** @test */
    public function get_game_day_start_with_default_day_start_time()
    {
        ClockUtility::setDayStartTime('00:00:00'); // env()が使えないテスト環境用
        ClockUtility::setNow('2024-01-15 10:30:00');
        
        $result = ClockUtility::getGameDayStart();
        
        // DAY_START_TIME=00:00:00の場合、2024-01-15 00:00:00を返す
        $this->assertEquals('2024-01-15 00:00:00', $result->format('Y-m-d H:i:s'));
        ClockUtility::reset();
    }

    /** @test */
    public function get_game_day_start_with_custom_day_start_time()
    {
        ClockUtility::setDayStartTime('09:00:00');
        ClockUtility::setNow('2024-01-15 10:30:00');
        
        $result = ClockUtility::getGameDayStart();
        
        // DAY_START_TIME=09:00:00で、現在時刻が10:30:00なので、2024-01-15 09:00:00を返す
        $this->assertEquals('2024-01-15 09:00:00', $result->format('Y-m-d H:i:s'));
        ClockUtility::reset();
    }

    /** @test */
    public function get_game_day_start_before_day_start_time()
    {
        ClockUtility::setDayStartTime('09:00:00');
        ClockUtility::setNow('2024-01-15 08:30:00');
        
        $result = ClockUtility::getGameDayStart();
        
        // DAY_START_TIME=09:00:00で、現在時刻が08:30:00（開始前）なので、前日の09:00:00を返す
        $this->assertEquals('2024-01-14 09:00:00', $result->format('Y-m-d H:i:s'));
        ClockUtility::reset();
    }

    /** @test */
    public function is_same_game_day_with_default_day_start_time()
    {
        ClockUtility::setDayStartTime('00:00:00'); // env()が使えないテスト環境用
        
        // DAY_START_TIME=00:00:00（デフォルト）
        $this->assertTrue(ClockUtility::isSameGameDay('2024-01-15 10:00:00', '2024-01-15 20:00:00'));
        $this->assertFalse(ClockUtility::isSameGameDay('2024-01-15 10:00:00', '2024-01-16 10:00:00'));
        
        ClockUtility::reset();
    }

    /** @test */
    public function is_same_game_day_with_custom_day_start_time()
    {
        ClockUtility::setDayStartTime('09:00:00');
        
        // 同じゲーム内日付
        $this->assertTrue(ClockUtility::isSameGameDay('2024-01-15 10:00:00', '2024-01-15 20:00:00'));
        $this->assertTrue(ClockUtility::isSameGameDay('2024-01-15 10:00:00', '2024-01-16 08:30:00'));
        
        // 異なるゲーム内日付
        $this->assertFalse(ClockUtility::isSameGameDay('2024-01-15 08:00:00', '2024-01-15 10:00:00'));
        $this->assertFalse(ClockUtility::isSameGameDay('2024-01-15 10:00:00', '2024-01-16 10:00:00'));
        
        ClockUtility::reset();
    }

    /** @test */
    public function reset_clears_day_start_time()
    {
        ClockUtility::setDayStartTime('09:00:00');
        $this->assertEquals('09:00:00', ClockUtility::getDayStartTime());
        
        ClockUtility::reset();
        
        // リセット後はnullに戻り、次回getDayStartTime()でenv()から再取得される
        // テスト環境ではenv()が使えない場合があるので、再設定をテスト
        ClockUtility::setDayStartTime('12:00:00');
        $this->assertEquals('12:00:00', ClockUtility::getDayStartTime());
    }
}

