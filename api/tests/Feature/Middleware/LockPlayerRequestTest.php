<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\LockPlayerRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 同じプレイヤーの書き込みリクエストを、1つずつ処理する
 */
class LockPlayerRequestTest extends TestCase
{
    private function request(string $method, ?int $playerId): Request
    {
        $request = Request::create('/api/item/use', $method);
        if ($playerId !== null) {
            $request->attributes->set('authenticated_player_id', $playerId);
        }

        return $request;
    }

    #[Test]
    public function test_lock_is_held_during_request_and_released_after(): void
    {
        $heldDuring = null;

        $response = (new LockPlayerRequest)->handle($this->request('POST', 1), function () use (&$heldDuring) {
            // 処理中は、同じプレイヤーのロックを取れない
            $lock = Cache::lock('player_request:1', 5);
            $heldDuring = ! $lock->get();

            return response()->json(['ok' => true]);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($heldDuring);

        // 終わったら外れている
        $lock = Cache::lock('player_request:1', 5);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    #[Test]
    public function test_lock_is_released_when_request_throws(): void
    {
        try {
            (new LockPlayerRequest)->handle($this->request('POST', 2), fn () => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
        }

        $lock = Cache::lock('player_request:2', 5);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    #[Test]
    public function test_returns_409_when_another_request_holds_the_lock(): void
    {
        config(['security.player_lock.wait_seconds' => 0]);

        // 同じプレイヤーの別リクエストが処理中
        $other = Cache::lock('player_request:3', 30);
        $this->assertTrue($other->get());

        $called = false;
        $response = (new LockPlayerRequest)->handle($this->request('POST', 3), function () use (&$called) {
            $called = true;

            return response()->json(['ok' => true]);
        });

        $this->assertSame(409, $response->getStatusCode());
        $this->assertFalse($called);

        // 別のプレイヤーは待たされない
        $anotherPlayer = (new LockPlayerRequest)->handle($this->request('POST', 4), fn () => response()->json(['ok' => true]));
        $this->assertSame(200, $anotherPlayer->getStatusCode());

        $other->release();
    }

    #[Test]
    public function test_get_and_unauthenticated_requests_are_not_locked(): void
    {
        $lock = Cache::lock('player_request:5', 30);
        $this->assertTrue($lock->get());

        $get = (new LockPlayerRequest)->handle($this->request('GET', 5), fn () => response()->json(['ok' => true]));
        $this->assertSame(200, $get->getStatusCode());

        $anonymous = (new LockPlayerRequest)->handle($this->request('POST', null), fn () => response()->json(['ok' => true]));
        $this->assertSame(200, $anonymous->getStatusCode());

        $lock->release();
    }
}
