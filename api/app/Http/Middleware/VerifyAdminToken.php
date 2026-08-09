<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * VerifyAdminToken Middleware
 *
 * 管理者APIへのアクセスを認証します。
 *
 * 認証方法:
 * 1. Authorization: Bearer {ADMIN_TOKEN} ヘッダーの検証
 * 2. オプション: IP制限（設定で有効化可能）
 *
 * 設定:
 * - ADMIN_TOKEN: .env で設定する管理者トークン
 * - ADMIN_ALLOWED_IPS: カンマ区切りの許可IPリスト（オプション）
 */
class VerifyAdminToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        \Log::info('Admin API access attempt', [
            'ip' => $request->ip(),
            'url' => $request->url(),
            'method' => $request->method(),
        ]);

        // 1. トークン検証
        $adminToken = config('auth.admin_token');

        if (! $adminToken) {
            \Log::error('ADMIN_TOKEN is not configured');

            return response()->json([
                'error_code' => 10500,
                'message' => 'Server configuration error',
            ], 500);
        }

        $authHeader = $request->header('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            \Log::warning('Admin API access denied: Missing or invalid Authorization header', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error_code' => 10401,
                'message' => 'Unauthorized',
            ], 401);
        }

        $token = substr($authHeader, 7); // Remove "Bearer " prefix

        if (! hash_equals($adminToken, $token)) {
            \Log::warning('Admin API access denied: Invalid token', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error_code' => 10401,
                'message' => 'Unauthorized',
            ], 401);
        }

        // 2. IP制限（オプション）
        $allowedIps = config('auth.admin_allowed_ips');

        if (! empty($allowedIps)) {
            $allowedIpsArray = array_map('trim', explode(',', $allowedIps));
            $requestIp = $request->ip();

            if (! in_array($requestIp, $allowedIpsArray, true)) {
                \Log::warning('Admin API access denied: IP not allowed', [
                    'ip' => $requestIp,
                    'allowed_ips' => $allowedIpsArray,
                ]);

                return response()->json([
                    'error_code' => 10403,
                    'message' => 'Forbidden',
                ], 403);
            }
        }

        \Log::info('Admin API access granted', [
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
