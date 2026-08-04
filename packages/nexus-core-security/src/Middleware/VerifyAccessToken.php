<?php

namespace NexusSecurity\Middleware;

use NexusSecurity\Contracts\TokenValidatorInterface;
use NexusSecurity\Contracts\PlayerSessionInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * VerifyAccessToken Middleware
 * 
 * アクセストークンを検証し、リクエストにプレイヤー情報を追加する
 * PlayerSessionにプレイヤーIDを設定してアプリケーション全体で利用可能にする
 * 
 * 使用方法:
 * 1. TokenValidatorInterfaceを実装したクラスを作成
 * 2. PlayerSessionInterfaceを実装したクラスを作成（オプション）
 * 3. サービスプロバイダーでバインド:
 *    $this->app->bind(TokenValidatorInterface::class, YourTokenValidator::class);
 *    $this->app->bind(PlayerSessionInterface::class, YourPlayerSession::class);
 */
class VerifyAccessToken
{
    /**
     * @param TokenValidatorInterface $tokenValidator
     * @param PlayerSessionInterface|null $playerSession
     */
    public function __construct(
        private TokenValidatorInterface $tokenValidator,
        private ?PlayerSessionInterface $playerSession = null
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
        $payload = $this->tokenValidator->validateAccessToken($token);

        if (!$payload) {
            return response()->json([
                'error' => 'Invalid or expired access token'
            ], 401);
        }

        // リクエストにプレイヤー情報を追加
        $request->merge([
            'authenticated_player_id' => $payload['player_id'],
            'authenticated_uuid' => $payload['uuid'] ?? null,
        ]);

        // PlayerSessionにプレイヤーIDを設定（アプリケーション全体で利用可能）
        if ($this->playerSession) {
            $this->playerSession::setPlayerId($payload['player_id']);
        }

        return $next($request);
    }
}
