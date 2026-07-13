<?php

namespace LaravelSecurityMiddleware\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * ThrottleSignUp Middleware
 * 
 * sign_upエンドポイントへのレート制限を行います。
 * 
 * 制限の種類:
 * 1. IPアドレスごとの制限: 設定可能（デフォルト: 1時間に10回まで）
 * 2. デバイスIDごとの制限: 設定可能（デフォルト: 1時間に3回まで）
 * 
 * 連打攻撃やアカウント大量生成を防止します。
 */
class ThrottleSignUp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $deviceId = $request->input('device_id');
        
        $maxAttemptsPerIp = config('security.throttle_signup.max_attempts_per_ip', 10);
        $maxAttemptsPerDevice = config('security.throttle_signup.max_attempts_per_device', 3);
        $rateLimitWindow = config('security.throttle_signup.rate_limit_window', 3600);

        // 1. IPアドレスベースのレート制限
        $ipKey = "signup_rate_limit:ip:{$ip}";
        $ipAttempts = Cache::get($ipKey, 0);

        if ($ipAttempts >= $maxAttemptsPerIp) {
            return response()->json([
                'error' => 'TOO_MANY_REQUESTS',
                'message' => 'Too many sign up attempts from this IP address. Please try again later.',
                'retry_after' => Cache::get("{$ipKey}:ttl", $rateLimitWindow),
            ], 429);
        }

        // 2. デバイスIDベースのレート制限
        if ($deviceId) {
            $deviceKey = "signup_rate_limit:device:{$deviceId}";
            $deviceAttempts = Cache::get($deviceKey, 0);

            if ($deviceAttempts >= $maxAttemptsPerDevice) {
                return response()->json([
                    'error' => 'TOO_MANY_REQUESTS',
                    'message' => 'Too many sign up attempts from this device. Please try again later.',
                    'retry_after' => Cache::get("{$deviceKey}:ttl", $rateLimitWindow),
                ], 429);
            }
        }

        // リクエストを処理
        $response = $next($request);

        // 成功/失敗に関わらずカウントを増やす（DoS対策）
        // IPカウント
        $newIpAttempts = $ipAttempts + 1;
        Cache::put($ipKey, $newIpAttempts, $rateLimitWindow);
        Cache::put("{$ipKey}:ttl", $this->getRemainingSeconds($ipKey, $rateLimitWindow), $rateLimitWindow);

        // デバイスIDカウント
        if ($deviceId) {
            $newDeviceAttempts = ($deviceAttempts ?? 0) + 1;
            Cache::put($deviceKey, $newDeviceAttempts, $rateLimitWindow);
            Cache::put("{$deviceKey}:ttl", $this->getRemainingSeconds($deviceKey, $rateLimitWindow), $rateLimitWindow);
        }

        // レート制限情報をヘッダーに追加
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttemptsPerIp,
            'X-RateLimit-Remaining' => max(0, $maxAttemptsPerIp - $newIpAttempts),
        ]);
    }

    /**
     * キャッシュの残り時間を取得
     * 
     * @param string $key キャッシュキー
     * @param int $rateLimitWindow レート制限ウィンドウ
     * @return int 残り秒数
     */
    private function getRemainingSeconds(string $key, int $rateLimitWindow): int
    {
        // キャッシュの有効期限を取得（Laravelのキャッシュ実装に依存）
        // 正確な残り時間が取れない場合はウィンドウ全体の時間を返す
        return $rateLimitWindow;
    }
}
