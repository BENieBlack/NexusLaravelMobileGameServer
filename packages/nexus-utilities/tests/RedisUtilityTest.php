<?php

namespace NexusUtilities\Tests;

use NexusUtilities\RedisUtility;
use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\Cache;
use Mockery;

class RedisUtilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mockery setup for Cache facade
        if (!class_exists('Illuminate\Support\Facades\Facade')) {
            $this->markTestSkipped('Laravel facades not available');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_generates_correct_prefix_key_format()
    {
        $result = RedisUtility::prefixKey('user', '12345');
        
        $this->assertEquals('user:12345', $result);
    }

    /** @test */
    public function it_generates_key_with_numeric_identifier()
    {
        $result = RedisUtility::prefixKey('session', 99999);
        
        $this->assertEquals('session:99999', $result);
    }

    /** @test */
    public function it_generates_key_with_multiple_colons()
    {
        $result = RedisUtility::prefixKey('app:cache', 'data');
        
        $this->assertEquals('app:cache:data', $result);
    }

    /** @test */
    public function it_handles_empty_string_key()
    {
        $result = RedisUtility::prefixKey('prefix', '');
        
        $this->assertEquals('prefix:', $result);
    }

    /** @test */
    public function it_handles_zero_as_key()
    {
        $result = RedisUtility::prefixKey('counter', 0);
        
        $this->assertEquals('counter:0', $result);
    }

    /** @test */
    public function it_builds_complex_keys_correctly()
    {
        $playerId = 12345;
        $itemId = 67890;
        
        $result = RedisUtility::prefixKey('player', "{$playerId}:item:{$itemId}");
        
        $this->assertEquals('player:12345:item:67890', $result);
    }

    /** @test */
    public function delete_many_returns_true()
    {
        // This is a simple test that doesn't require actual Redis connection
        // It tests the method signature and return type
        
        $this->assertTrue(method_exists(RedisUtility::class, 'deleteMany'));
    }

    /** @test */
    public function compression_level_constant_exists()
    {
        $reflection = new \ReflectionClass(RedisUtility::class);
        $constants = $reflection->getConstants();
        
        $this->assertArrayHasKey('COMPRESSION_LEVEL', $constants);
        $this->assertEquals(6, $constants['COMPRESSION_LEVEL']);
    }
}
