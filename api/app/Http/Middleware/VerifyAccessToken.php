<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Services\TokenService;
use App\Persistence\ApiSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * VerifyAccessToken Middleware
 * 
 * アクセストークンを検証し、リクエストにプレイヤー情報を追加する
 * ApiSessionにプレイヤーIDを設定してアプリケーション全体で利用可能にする
 */
class VerifyAccessToken
{
    /**
     * @param TokenService $tokenService
     */
    public function __construct(
        private TokenService $tokenService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Authorizationヘッダーからトークンを取得
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'error' => 'Authorization header missing or invalid'
            ], 401);
        }

        // "Bearer " プレフィックスを除去
        $token = substr($authHeader, 7);

        // トークンを検証
        $payload = $this->tokenService->validateAccessToken($token);

        if (!$payload) {
            return response()->json([
                'error' => 'Invalid or expired access token'
            ], 401);
        }

        // リクエストにプレイヤー情報を追加
        $request->merge([
            'authenticated_player_id' => $payload['player_id'],
            'authenticated_uuid' => $payload['uuid'],
        ]);

        // ApiSessionにプレイヤーIDを設定（アプリケーション全体で利用可能）
        ApiSession::setSysPlayerId($payload['player_id']);

        return $next($request);
    }
}
