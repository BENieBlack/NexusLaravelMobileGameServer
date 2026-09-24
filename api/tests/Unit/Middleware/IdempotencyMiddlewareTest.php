<?php

namespace Tests\Unit\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use NexusSecurity\Middleware\IdempotencyMiddleware;
use Tests\TestCase;

class IdempotencyMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_skips_when_disabled()
    {
        Config::set('security.idempotency.enabled', false);

        $middleware = new IdempotencyMiddleware;
        $request = Request::create('/test', 'POST', [], [], [], [], '{"test":"data"}');

        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_skips_when_header_missing()
    {
        Config::set('security.idempotency.enabled', true);

        $middleware = new IdempotencyMiddleware;
        $request = Request::create('/test', 'POST', [], [], [], [], '{"test":"data"}');

        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_skips_for_get_requests()
    {
        Config::set('security.idempotency.enabled', true);

        $middleware = new IdempotencyMiddleware;
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

    public function test_skips_when_not_authenticated()
    {
        Config::set('security.idempotency.enabled', true);

        $middleware = new IdempotencyMiddleware;
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

    public function test_returns_cached_response()
    {
        Config::set('security.idempotency.enabled', true);
        Config::set('security.idempotency.cache_prefix', 'idempotency');

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

        $middleware = new IdempotencyMiddleware;
        $request = Request::create('/'.$path, 'POST', [], [], [], [], '{"test":"data"}');
        $request->attributes->set('authenticated_player_id', $playerId);
        $request->headers->set('X-Unique-Request-Identifier', $uniqueId);

        $response = $middleware->handle($request, function () {
            $this->fail('Next middleware should not be called when cache exists');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('HIT', $response->headers->get('X-Idempotency-Cache'));
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['result' => 'cached'], $data);
    }

    public function test_returns_conflict_while_previous_request_is_in_flight()
    {
        // 同じリクエストが処理中なら、二重に走らせず409で待たせる。
        // ここを通してしまうと同じ購入が2回処理される
        Config::set('security.idempotency.enabled', true);
        Config::set('security.idempotency.cache_prefix', 'idempotency');

        $cacheKey = 'idempotency:12345:in-flight:api:test';

        Cache::shouldReceive('has')->with($cacheKey)->andReturn(true);
        Cache::shouldReceive('get')->with($cacheKey)->andReturn('__PROCESSING__');

        $response = (new IdempotencyMiddleware)->handle(
            $this->authenticatedRequest('in-flight'),
            fn () => $this->fail('処理中なのに後続が呼ばれた')
        );

        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('PROCESSING', $response->headers->get('X-Idempotency-Cache'));
        $this->assertEquals(40900, json_decode($response->getContent(), true)['error_code']);
    }

    public function test_returns_conflict_when_another_request_reserves_first()
    {
        // has() では見つからなくても、add() の時点で他が先に予約していれば競合。
        // 同時に届いた2本のうち後発をここで止める
        Config::set('security.idempotency.enabled', true);
        Config::set('security.idempotency.cache_prefix', 'idempotency');

        Cache::shouldReceive('has')->andReturn(false);
        Cache::shouldReceive('add')->andReturn(false);

        $response = (new IdempotencyMiddleware)->handle(
            $this->authenticatedRequest('raced'),
            fn () => $this->fail('予約に失敗したのに後続が呼ばれた')
        );

        $this->assertEquals(409, $response->getStatusCode());
        $this->assertEquals('CONFLICT', $response->headers->get('X-Idempotency-Cache'));
    }

    public function test_releases_reservation_when_handler_throws()
    {
        // 例外で落ちたまま予約が残ると、リトライしても409のままになる
        Config::set('security.idempotency.enabled', true);
        Config::set('security.idempotency.cache_prefix', 'idempotency');

        $cacheKey = 'idempotency:12345:boom:api:test';

        Cache::shouldReceive('has')->with($cacheKey)->andReturn(false);
        Cache::shouldReceive('add')->andReturn(true);
        Cache::shouldReceive('forget')->with($cacheKey)->once();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handler exploded');

        (new IdempotencyMiddleware)->handle(
            $this->authenticatedRequest('boom'),
            fn () => throw new \RuntimeException('handler exploded')
        );
    }

    /**
     * 認証済みで冪等キー付きのPOSTリクエストを組む
     */
    private function authenticatedRequest(string $uniqueRequestId): Request
    {
        $request = Request::create('/api/test', 'POST', [], [], [], [], '{"test":"data"}');
        $request->attributes->set('authenticated_player_id', 12345);
        $request->headers->set('X-Unique-Request-Identifier', $uniqueRequestId);

        return $request;
    }

    public function test_caches_successful_response()
    {
        Config::set('security.idempotency.enabled', true);
        Config::set('security.idempotency.cache_prefix', 'idempotency');
        Config::set('security.idempotency.cache_ttl', 86400);
        Config::set('security.idempotency.compression_level', 6);

        $playerId = 67890;
        $uniqueId = 'new-request-id';
        $path = 'api/action';
        $cacheKey = "idempotency:{$playerId}:{$uniqueId}:api:action";

        // 実際のキャッシュドライバ（array）で検証する
        Cache::flush();

        $middleware = new IdempotencyMiddleware;
        $request = Request::create('/'.$path, 'POST', [], [], [], [], '{"test":"data"}');
        $request->attributes->set('authenticated_player_id', $playerId);
        $request->headers->set('X-Unique-Request-Identifier', $uniqueId);

        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['result' => 'success'], 200);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('MISS', $response->headers->get('X-Idempotency-Cache'));

        // 成功レスポンスはキャッシュに保存される
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_does_not_cache_error_response()
    {
        Config::set('security.idempotency.enabled', true);
        Config::set('security.idempotency.cache_prefix', 'idempotency');

        $playerId = 11111;
        $uniqueId = 'error-request-id';
        $path = 'api/error';
        $cacheKey = "idempotency:{$playerId}:{$uniqueId}:api:error";

        // 実際のキャッシュドライバ（array）で検証する
        Cache::flush();

        $middleware = new IdempotencyMiddleware;
        $request = Request::create('/'.$path, 'POST', [], [], [], [], '{"test":"data"}');
        $request->attributes->set('authenticated_player_id', $playerId);
        $request->headers->set('X-Unique-Request-Identifier', $uniqueId);

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['error' => 'Something went wrong'], 400);
        });

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('MISS', $response->headers->get('X-Idempotency-Cache'));

        // エラーレスポンスはキャッシュされない（処理中フラグも消える）
        $this->assertFalse(Cache::has($cacheKey));
    }
}
