<?php

namespace NexusAuth\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * TokenDto DTO
 * 
 * アクセストークン、リフレッシュトークン、有効期限をまとめたDTO
 * OAuth2標準のトークンレスポンス形式に準拠
 */
readonly class TokenDto implements Arrayable, JsonSerializable
{
    /**
     * @param string $accessToken アクセストークン（JWT形式推奨）
     * @param string $refreshToken リフレッシュトークン
     * @param int $expiresIn アクセストークンの有効期限（秒）
     */
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
    ) {
    }

    /**
     * アクセストークン取得
     *
     * @return string
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * リフレッシュトークン取得
     *
     * @return string
     */
    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    /**
     * 有効期限（秒）取得
     *
     * @return int
     */
    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_in' => $this->expiresIn,
        ];
    }

    /**
     * JSON シリアライズ用
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
