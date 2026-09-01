<?php

namespace NexusSecurity\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * ThrottlePublicRead Middleware
 *
 * 認証を必要としない参照系エンドポイントのレート制限を行う。
 *
 * 認証が無いぶん呼び出し元を特定できないため、IPアドレス単位で数える。
 * ギルド一覧やメンバー一覧のように、繰り返し叩けば
 * 他プレイヤーの情報を集められる種類のエンドポイントに掛ける。
 *
 * 使い方: ->middleware('throttle.public:guild_read')
 */
class ThrottlePublicRead
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $name  制限の名前（ログとキーの区別に使う）
     */
    public function handle(Request $request, Closure $next, string $name = 'public'): Response
    {
        if (! config('security.throttle_public_read.enabled', true)) {
            return $next($request);
        }

        $max = (int) config('security.throttle_public_read.max_attempts_per_ip', 60);
        $window = (int) config('security.throttle_public_read.rate_limit_window', 60);

        $key = "throttle_public_read:{$name}:ip:".$request->ip();

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $retryAfter = RateLimiter::availableIn($key);

            Log::warning('ThrottlePublicRead: rate limit exceeded', [
                'name' => $name,
                'ip' => $request->ip(),
                'retry_after' => $retryAfter,
            ]);

            return response()->json([
                'error' => 'TOO_MANY_REQUESTS',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $max,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($key, $window);

        $response = $next($request);

        // $next() は BinaryFileResponse など withHeaders() を持たない
        // Symfony の Response を返すこともあるので、HeaderBag に直接入れる
        $response->headers->set('X-RateLimit-Limit', (string) $max);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($key, $max));

        return $response;
    }
}
