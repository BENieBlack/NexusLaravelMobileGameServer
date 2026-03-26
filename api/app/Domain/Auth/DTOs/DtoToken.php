<?php

namespace App\Domain\Auth\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * トークン情報DTO
 * 
 * アクセストークン、リフレッシュトークン、有効期限をまとめたDTO
 */
readonly class DtoToken implements Arrayable, JsonSerializable
{
    /**
     * @param string $accessToken アクセストークン（JWT）
     * @param string $refreshToken リフレッシュトークン（平文）
     * @param int $expiresIn アクセストークンの有効期限（秒）
     */
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
    ) {
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
