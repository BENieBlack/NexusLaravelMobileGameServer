<?php

namespace NexusSecurity\Middleware;

use Closure;
use Illuminate\Http\Request;
use NexusSecurity\Contracts\PlayerSessionInterface;
use NexusSecurity\Contracts\TokenValidatorInterface;
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
     * @param  TokenValidatorInterface  $tokenValidator
     * @param  PlayerSessionInterface|null  $playerSession
     */
    public function __construct(
        private TokenValidatorInterface $tokenValidator,
        private ?PlayerSessionInterface $playerSession = null
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Authorizationヘッダーからトークンを取得
        $authHeader = $request->headers->get('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'error' => 'Authorization header missing or invalid',
            ], 401);
        }

        // "Bearer " プレフィックスを除去
        $token = substr($authHeader, 7);

        // トークンを検証
        $payload = $this->tokenValidator->validateAccessToken($token);

        if (! $payload) {
            return response()->json([
                'error' => 'Invalid or expired access token',
            ], 401);
        }

        // 認証情報はリクエスト属性に設定する
        // input()に混ぜるとクライアント入力と区別が付かず、
        // ミドルウェアの適用漏れがそのまま認証バイパスになるため
        $request->attributes->set('authenticated_player_id', $payload['player_id']);
        $request->attributes->set('authenticated_uuid', $payload['uuid'] ?? null);

        // PlayerSessionにプレイヤーIDを設定（アプリケーション全体で利用可能）
        if ($this->playerSession) {
            $this->playerSession::setPlayerId($payload['player_id']);
        }

        return $next($request);
    }
}
