<?php

namespace Tests\Unit\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use NexusSecurity\Middleware\VerifyClientSignature;
use Tests\TestCase;

class VerifyClientSignatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_missing_required_headers()
    {
        Config::set('security.client_signature.timestamp_tolerance', 300);

        $middleware = new VerifyClientSignature;
        $request = Request::create('/test', 'POST', [], [], [], [], '{"test":"data"}');

        $response = $middleware->handle($request, function () {
            $this->fail('Next middleware should not be called');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('INVALID_CLIENT_REQUEST', $data['error']);
    }

    public function test_expired_timestamp()
    {
        Config::set('security.client_signature.timestamp_tolerance', 300);
        Cache::shouldReceive('has')->andReturn(false);

        $middleware = new VerifyClientSignature;
        $request = Request::create('/test', 'POST', [], [], [], [], '{"test":"data"}');

        // 古すぎるタイムスタンプ
        $oldTimestamp = time() - 400;
        $request->headers->set('X-Client-Timestamp', (string) $oldTimestamp);
        $request->headers->set('X-Client-Nonce', 'test-nonce-123');
        $request->headers->set('X-Client-Signature', 'dummy-signature');

        $response = $middleware->handle($request, function () {
            $this->fail('Next middleware should not be called');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('REQUEST_EXPIRED', $data['error']);
    }

    public function test_duplicate_nonce()
    {
        Config::set('security.client_signature.timestamp_tolerance', 300);
        Cache::shouldReceive('has')->with('client_nonce:duplicate-nonce')->andReturn(true);

        $middleware = new VerifyClientSignature;
        $request = Request::create('/test', 'POST', [], [], [], [], '{"test":"data"}');

        $request->headers->set('X-Client-Timestamp', (string) time());
        $request->headers->set('X-Client-Nonce', 'duplicate-nonce');
        $request->headers->set('X-Client-Signature', 'dummy-signature');

        $response = $middleware->handle($request, function () {
            $this->fail('Next middleware should not be called');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('DUPLICATE_REQUEST', $data['error']);
    }

    public function test_invalid_signature()
    {
        Config::set('security.client_signature.timestamp_tolerance', 300);
        Config::set('security.client_signature.secret', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        Config::set('app.client_secret', null);
        Cache::shouldReceive('has')->with('client_nonce:test-nonce')->andReturn(false);

        $middleware = new VerifyClientSignature;
        $request = Request::create('/test', 'POST', [], [], [], [], '{"test":"data"}');

        $timestamp = time();
        $request->headers->set('X-Client-Timestamp', (string) $timestamp);
        $request->headers->set('X-Client-Nonce', 'test-nonce');
        $request->headers->set('X-Client-Signature', 'invalid-signature');

        $response = $middleware->handle($request, function () {
            $this->fail('Next middleware should not be called');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('INVALID_SIGNATURE', $data['error']);
    }

    public function test_valid_signature()
    {
        $timestamp = time();
        $nonce = 'valid-nonce-123';
        $body = '{"test":"data"}';
        $secret = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

        // 正しい署名を生成
        $message = "{$timestamp}:{$nonce}:{$body}";
        $validSignature = hash_hmac('sha256', $message, $secret);

        Config::set('security.client_signature.timestamp_tolerance', 300);
        Config::set('security.client_signature.secret', $secret);
        Config::set('app.client_secret', null);
        Config::set('security.client_signature.nonce_cache_ttl', 600);

        Cache::shouldReceive('has')->with("client_nonce:{$nonce}")->andReturn(false);
        Cache::shouldReceive('put')->with("client_nonce:{$nonce}", true, 600)->once();

        $middleware = new VerifyClientSignature;
        $request = Request::create('/test', 'POST', [], [], [], [], $body);

        $request->headers->set('X-Client-Timestamp', (string) $timestamp);
        $request->headers->set('X-Client-Nonce', $nonce);
        $request->headers->set('X-Client-Signature', $validSignature);

        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
