<?php

namespace Tests\Unit\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use NexusSecurity\Middleware\ThrottleSignUp;
use Tests\TestCase;

class ThrottleSignUpTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_allows_request_within_ip_limit()
    {
        $ip = '192.168.1.1';
        $ipKey = "signup_rate_limit:ip:{$ip}";

        Config::set('security.throttle_signup.max_attempts_per_ip', 10);
        Config::set('security.throttle_signup.max_attempts_per_device', 3);
        Config::set('security.throttle_signup.rate_limit_window', 3600);

        Cache::shouldReceive('get')->with($ipKey, 0)->andReturn(0);
        Cache::shouldReceive('put')->with($ipKey, 1, 3600)->once();
        Cache::shouldReceive('put')->with("{$ipKey}:ttl", 3600, 3600)->once();

        $middleware = new ThrottleSignUp;
        $request = Request::create('/sign_up', 'POST', [], [], [], ['REMOTE_ADDR' => $ip], '{"device_id":"test-device"}');

        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(10, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(9, $response->headers->get('X-RateLimit-Remaining'));
    }

    public function test_blocks_request_when_ip_limit_exceeded()
    {
        $ip = '192.168.1.2';
        $ipKey = "signup_rate_limit:ip:{$ip}";

        Config::set('security.throttle_signup.max_attempts_per_ip', 10);
        Config::set('security.throttle_signup.max_attempts_per_device', 3);
        Config::set('security.throttle_signup.rate_limit_window', 3600);

        Cache::shouldReceive('get')->with($ipKey, 0)->andReturn(10); // 上限に達している
        Cache::shouldReceive('get')->with("{$ipKey}:ttl", 3600)->andReturn(1800);

        $middleware = new ThrottleSignUp;
        $request = Request::create('/sign_up', 'POST', [], [], [], ['REMOTE_ADDR' => $ip], '{"device_id":"test-device"}');

        $response = $middleware->handle($request, function () {
            $this->fail('Next middleware should not be called');
        });

        $this->assertEquals(429, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('TOO_MANY_REQUESTS', $data['error']);
        $this->assertEquals(1800, $data['retry_after']);
    }

    public function test_blocks_request_when_device_limit_exceeded()
    {
        $ip = '192.168.1.3';
        $deviceId = 'device-abc-123';
        $ipKey = "signup_rate_limit:ip:{$ip}";
        $deviceKey = "signup_rate_limit:device:{$deviceId}";

        Config::set('security.throttle_signup.max_attempts_per_ip', 10);
        Config::set('security.throttle_signup.max_attempts_per_device', 3);
        Config::set('security.throttle_signup.rate_limit_window', 3600);

        Cache::shouldReceive('get')->with($ipKey, 0)->andReturn(2); // IP制限内
        Cache::shouldReceive('get')->with($deviceKey, 0)->andReturn(3); // デバイス制限に達している
        Cache::shouldReceive('get')->with("{$deviceKey}:ttl", 3600)->andReturn(2400);

        $middleware = new ThrottleSignUp;
        $request = Request::create('/sign_up', 'POST', ['device_id' => $deviceId], [], [], ['REMOTE_ADDR' => $ip]);

        $response = $middleware->handle($request, function () {
            $this->fail('Next middleware should not be called');
        });

        $this->assertEquals(429, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('TOO_MANY_REQUESTS', $data['error']);
        $this->assertStringContainsString('device', $data['message']);
        $this->assertEquals(2400, $data['retry_after']);
    }

    public function test_increments_counters_for_both_ip_and_device()
    {
        $ip = '192.168.1.4';
        $deviceId = 'device-xyz-789';
        $ipKey = "signup_rate_limit:ip:{$ip}";
        $deviceKey = "signup_rate_limit:device:{$deviceId}";

        Config::set('security.throttle_signup.max_attempts_per_ip', 10);
        Config::set('security.throttle_signup.max_attempts_per_device', 3);
        Config::set('security.throttle_signup.rate_limit_window', 3600);

        Cache::shouldReceive('get')->with($ipKey, 0)->andReturn(5);
        Cache::shouldReceive('get')->with($deviceKey, 0)->andReturn(1);

        // IPカウンターを更新
        Cache::shouldReceive('put')->with($ipKey, 6, 3600)->once();
        Cache::shouldReceive('put')->with("{$ipKey}:ttl", 3600, 3600)->once();

        // デバイスカウンターを更新
        Cache::shouldReceive('put')->with($deviceKey, 2, 3600)->once();
        Cache::shouldReceive('put')->with("{$deviceKey}:ttl", 3600, 3600)->once();

        $middleware = new ThrottleSignUp;
        $request = Request::create('/sign_up', 'POST', ['device_id' => $deviceId], [], [], ['REMOTE_ADDR' => $ip]);

        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(10, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(4, $response->headers->get('X-RateLimit-Remaining')); // 10 - 6 = 4
    }

    public function test_works_without_device_id()
    {
        $ip = '192.168.1.5';
        $ipKey = "signup_rate_limit:ip:{$ip}";

        Config::set('security.throttle_signup.max_attempts_per_ip', 10);
        Config::set('security.throttle_signup.max_attempts_per_device', 3);
        Config::set('security.throttle_signup.rate_limit_window', 3600);

        Cache::shouldReceive('get')->with($ipKey, 0)->andReturn(3);
        Cache::shouldReceive('put')->with($ipKey, 4, 3600)->once();
        Cache::shouldReceive('put')->with("{$ipKey}:ttl", 3600, 3600)->once();

        // デバイスIDがない場合はデバイスキーのキャッシュ操作は行われない

        $middleware = new ThrottleSignUp;
        $request = Request::create('/sign_up', 'POST', [], [], [], ['REMOTE_ADDR' => $ip]);

        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
