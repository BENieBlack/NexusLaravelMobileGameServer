<?php

namespace NexusSecurity\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * ThrottleAuth Middleware
 *
 * sign_in / refresh_token など、認証情報を受け取るエンドポイントの
 * レート制限を行う。
 *
 * 制限の種類:
 * 1. IPアドレスごとの制限（分散元からの総当たりを抑制）
 * 2. 資格情報ごとの制限（特定アカウント・トークンへの集中攻撃を抑制）
 *
 * 成否に関わらず試行を数えるため、応答からアカウントの存在有無は判別できない。
 *
 * 使い方: ->middleware('throttle.auth:sign_in')
 */
class ThrottleAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $name  制限の名前（ログとキーの区別に使う）
     */
    public function handle(Request $request, Closure $next, string $name = 'auth'): Response
    {
        if (! config('security.throttle_auth.enabled', true)) {
            return $next($request);
        }

        $maxPerIp = (int) config('security.throttle_auth.max_attempts_per_ip', 30);
        $maxPerCredential = (int) config('security.throttle_auth.max_attempts_per_credential', 10);
        $window = (int) config('security.throttle_auth.rate_limit_window', 900);

        $limits = [
            ['key' => "throttle_auth:{$name}:ip:".$request->ip(), 'max' => $maxPerIp, 'scope' => 'ip'],
        ];

        $credential = $this->credentialKey($request);

        if ($credential !== null) {
            $limits[] = [
                'key' => "throttle_auth:{$name}:credential:{$credential}",
                'max' => $maxPerCredential,
                'scope' => 'credential',
            ];
        }

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit['key'], $limit['max'])) {
                $retryAfter = RateLimiter::availableIn($limit['key']);

                Log::warning('ThrottleAuth: rate limit exceeded', [
                    'name' => $name,
                    'scope' => $limit['scope'],
                    'ip' => $request->ip(),
                    'retry_after' => $retryAfter,
                ]);

                return response()->json([
                    'error' => 'TOO_MANY_REQUESTS',
                    'message' => 'Too many authentication attempts. Please try again later.',
                    'retry_after' => $retryAfter,
                ], 429)->withHeaders([
                    'Retry-After' => $retryAfter,
                    'X-RateLimit-Limit' => $limit['max'],
                    'X-RateLimit-Remaining' => 0,
                ]);
            }
        }

        // 成否に関わらず試行を記録する
        foreach ($limits as $limit) {
            RateLimiter::hit($limit['key'], $window);
        }

        $response = $next($request);

        // $next() は BinaryFileResponse など withHeaders() を持たない
        // Symfony の Response を返すこともあるので、HeaderBag に直接入れる
        $response->headers->set('X-RateLimit-Limit', (string) $maxPerIp);
        $response->headers->set(
            'X-RateLimit-Remaining',
            (string) RateLimiter::remaining($limits[0]['key'], $maxPerIp)
        );

        return $response;
    }

    /**
     * 資格情報ごとの制限に使うキーを取得
     *
     * 生の値はキャッシュに残さず、ハッシュ化して使う
     */
    private function credentialKey(Request $request): ?string
    {
        $value = $request->input('device_id') ?? $request->input('refresh_token');

        if (! is_string($value) || $value === '') {
            return null;
        }

        return hash('sha256', $value);
    }
}
