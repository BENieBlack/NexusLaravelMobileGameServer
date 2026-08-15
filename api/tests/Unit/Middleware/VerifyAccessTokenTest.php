<?php

namespace Tests\Unit\Middleware;

use Illuminate\Http\Request;
use Mockery;
use NexusSecurity\Contracts\PlayerSessionInterface;
use NexusSecurity\Contracts\TokenValidatorInterface;
use NexusSecurity\Middleware\VerifyAccessToken;
use Tests\TestCase;

class VerifyAccessTokenTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_missing_authorization_header()
    {
        $tokenValidator = Mockery::mock(TokenValidatorInterface::class);
        $middleware = new VerifyAccessToken($tokenValidator);

        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function () {
            $this->fail('Next middleware should not be called');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Authorization header', $data['error']);
    }

    public function test_invalid_authorization_header_format()
    {
        $tokenValidator = Mockery::mock(TokenValidatorInterface::class);
        $middleware = new VerifyAccessToken($tokenValidator);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'InvalidFormat abc123');

        $response = $middleware->handle($request, function () {
            $this->fail('Next middleware should not be called');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Authorization header', $data['error']);
    }

    public function test_invalid_token()
    {
        $tokenValidator = Mockery::mock(TokenValidatorInterface::class);
        $tokenValidator->shouldReceive('validateAccessToken')
            ->once()
            ->with('invalid_token')
            ->andReturn(null);

        $middleware = new VerifyAccessToken($tokenValidator);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer invalid_token');

        $response = $middleware->handle($request, function () {
            $this->fail('Next middleware should not be called');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Invalid or expired', $data['error']);
    }

    public function test_valid_token_without_player_session()
    {
        $tokenValidator = Mockery::mock(TokenValidatorInterface::class);
        $tokenValidator->shouldReceive('validateAccessToken')
            ->once()
            ->with('valid_token')
            ->andReturn([
                'player_id' => 12345,
                'uuid' => 'test-uuid-123',
            ]);

        $middleware = new VerifyAccessToken($tokenValidator);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer valid_token');

        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            // リクエストにプレイヤー情報が追加されていることを確認
            $this->assertEquals(12345, $req->attributes->get('authenticated_player_id'));
            $this->assertEquals('test-uuid-123', $req->attributes->get('authenticated_uuid'));

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_valid_token_with_player_session()
    {
        $tokenValidator = Mockery::mock(TokenValidatorInterface::class);
        $tokenValidator->shouldReceive('validateAccessToken')
            ->once()
            ->with('valid_token')
            ->andReturn([
                'player_id' => 67890,
                'uuid' => 'test-uuid-456',
            ]);

        $playerSession = Mockery::mock(PlayerSessionInterface::class);
        $playerSession->shouldReceive('setPlayerId')
            ->once()
            ->with(67890);

        $middleware = new VerifyAccessToken($tokenValidator, $playerSession);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer valid_token');

        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            // リクエストにプレイヤー情報が追加されていることを確認
            $this->assertEquals(67890, $req->attributes->get('authenticated_player_id'));
            $this->assertEquals('test-uuid-456', $req->attributes->get('authenticated_uuid'));

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_valid_token_without_uuid()
    {
        $tokenValidator = Mockery::mock(TokenValidatorInterface::class);
        $tokenValidator->shouldReceive('validateAccessToken')
            ->once()
            ->with('valid_token_no_uuid')
            ->andReturn([
                'player_id' => 99999,
            ]);

        $middleware = new VerifyAccessToken($tokenValidator);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer valid_token_no_uuid');

        $nextCalled = false;
        $response = $middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            // UUIDがnullでもエラーにならないことを確認
            $this->assertEquals(99999, $req->attributes->get('authenticated_player_id'));
            $this->assertNull($req->attributes->get('authenticated_uuid'));

            return response()->json(['success' => true]);
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
