<?php

namespace NexusAuth\Services;

use NexusSecurity\Contracts\TokenValidatorInterface;

/**
 * TokenValidator
 * 
 * NexusSecurityのTokenValidatorInterfaceを実装
 * TokenServiceをラップしてミドルウェアで使用可能にする
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
     * @return array<string, mixed>|null ペイロード（player_id, uuid等）、無効な場合はnull
     */
    public function validateAccessToken(string $token): ?array
    {
        return $this->tokenService->validateAccessToken($token);
    }
}
