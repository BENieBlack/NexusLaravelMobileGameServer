<?php

namespace LaravelUtilities\Tests;

use LaravelUtilities\ClockUtility;
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
}
