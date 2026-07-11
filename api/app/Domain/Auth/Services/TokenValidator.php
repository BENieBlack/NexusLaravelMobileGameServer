<?php

namespace App\Domain\Auth\Services;

use LaravelSecurityMiddleware\Contracts\TokenValidatorInterface;

/**
 * TokenValidator
 * 
 * LaravelSecurityMiddlewareのTokenValidatorInterfaceを実装
 * 既存のTokenServiceをラップして使用
 */
class TokenValidator implements TokenValidatorInterface
{
    /**
     * @param TokenService $tokenService
     */
    public function __construct(
        private readonly TokenService $tokenService
    ) {}

    /**
     * アクセストークンを検証する
     *
     * @param string $token アクセストークン
     * @return array|null ペイロード（player_id, uuid等）、無効な場合はnull
     */
    public function validateAccessToken(string $token): ?array
    {
        return $this->tokenService->validateAccessToken($token);
    }
}
