<?php

namespace LaravelSecurityMiddleware\Tests;

use LaravelSecurityMiddleware\Middleware\IdempotencyMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\TestCase;
use Mockery;

class IdempotencyMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSkipsWhenDisabled()
    {
        Config::shouldReceive('get')->with('security.idempotency.enabled', true)->andReturn(false);
        
        $middleware = new IdempotencyMiddleware();
        $request = Request::create('/test', 'POST', [], [], [], [], '{"test":"data"}');
        
        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSkipsWhenHeaderMissing()
    {
        Config::shouldReceive('get')->with('security.idempotency.enabled', true)->andReturn(true);
        
        $middleware = new IdempotencyMiddleware();
        $request = Request::create('/test', 'POST', [], [], [], [], '{"test":"data"}');
        
        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSkipsForGetRequests()
    {
        Config::shouldReceive('get')->with('security.idempotency.enabled', true)->andReturn(true);
        
        $middleware = new IdempotencyMiddleware();
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Unique-Request-Identifier', 'unique-id-123');
        
        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSkipsWhenNotAuthenticated()
    {
        Config::shouldReceive('get')->with('security.idempotency.enabled', true)->andReturn(true);
        
        $middleware = new IdempotencyMiddleware();
        $request = Request::create('/test', 'POST', [], [], [], [], '{"test":"data"}');
        $request->headers->set('X-Unique-Request-Identifier', 'unique-id-123');
        // authenticated_player_id が設定されていない
        
        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testReturnsCachedResponse()
    {
        Config::shouldReceive('get')->with('security.idempotency.enabled', true)->andReturn(true);
        Config::shouldReceive('get')->with('security.idempotency.cache_prefix', 'idempotency')->andReturn('idempotency');
        
        $playerId = 12345;
        $uniqueId = 'cached-request-id';
        $path = 'api/test';
        $cacheKey = "idempotency:{$playerId}:{$uniqueId}:api:test";
        
        // キャッシュされたレスポンスデータ
        $cachedData = [
            'data' => ['result' => 'cached'],
            'status' => 200,
            'headers' => ['Content-Type' => 'application/json'],
        ];
        $compressed = gzencode(json_encode($cachedData), 6);
        
        Cache::shouldReceive('has')->with($cacheKey)->andReturn(true);
        Cache::shouldReceive('get')->with($cacheKey)->andReturn($compressed);
        
        $middleware = new IdempotencyMiddleware();
        $request = Request::create('/' . $path, 'POST', ['authenticated_player_id' => $playerId], [], [], [], '{"test":"data"}');
        $request->headers->set('X-Unique-Request-Identifier', $uniqueId);
        
        $response = $middleware->handle($request, function () {
            $this->fail('Next middleware should not be called when cache exists');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('HIT', $response->headers->get('X-Idempotency-Cache'));
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['result' => 'cached'], $data);
    }

    public function testCachesSuccessfulResponse()
    {
        Config::shouldReceive('get')->with('security.idempotency.enabled', true)->andReturn(true);
        Config::shouldReceive('get')->with('security.idempotency.cache_prefix', 'idempotency')->andReturn('idempotency');
        Config::shouldReceive('get')->with('security.idempotency.cache_ttl', 86400)->andReturn(86400);
        Config::shouldReceive('get')->with('security.idempotency.compression_level', 6)->andReturn(6);
        
        $playerId = 67890;
        $uniqueId = 'new-request-id';
        $path = 'api/action';
        $cacheKey = "idempotency:{$playerId}:{$uniqueId}:api:action";
        
        Cache::shouldReceive('has')->with($cacheKey)->andReturn(false);
        Cache::shouldReceive('put')->withArgs(function ($key, $data, $ttl) use ($cacheKey) {
            return $key === $cacheKey && $ttl === 86400;
        })->once();
        
        $middleware = new IdempotencyMiddleware();
        $request = Request::create('/' . $path, 'POST', ['authenticated_player_id' => $playerId], [], [], [], '{"test":"data"}');
        $request->headers->set('X-Unique-Request-Identifier', $uniqueId);
        
        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return response()->json(['result' => 'success'], 200);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('MISS', $response->headers->get('X-Idempotency-Cache'));
    }

    public function testDoesNotCacheErrorResponse()
    {
        Config::shouldReceive('get')->with('security.idempotency.enabled', true)->andReturn(true);
        Config::shouldReceive('get')->with('security.idempotency.cache_prefix', 'idempotency')->andReturn('idempotency');
        
        $playerId = 11111;
        $uniqueId = 'error-request-id';
        $path = 'api/error';
        $cacheKey = "idempotency:{$playerId}:{$uniqueId}:api:error";
        
        Cache::shouldReceive('has')->with($cacheKey)->andReturn(false);
        // エラーレスポンスはキャッシュされないのでputは呼ばれない
        Cache::shouldReceive('put')->never();
        
        $middleware = new IdempotencyMiddleware();
        $request = Request::create('/' . $path, 'POST', ['authenticated_player_id' => $playerId], [], [], [], '{"test":"data"}');
        $request->headers->set('X-Unique-Request-Identifier', $uniqueId);
        
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['error' => 'Something went wrong'], 400);
        });

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('MISS', $response->headers->get('X-Idempotency-Cache'));
    }
}
