<?php

namespace NexusAuth\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * 認証トークン Value Object
 *
 * アクセストークン、リフレッシュトークン、有効期限をまとめた不変オブジェクト
 * OAuth2標準のトークンレスポンス形式に準拠
 */
final class Token implements Arrayable, JsonSerializable
{
    /**
     * @param  string  $accessToken  アクセストークン（JWT形式推奨）
     * @param  string  $refreshToken  リフレッシュトークン
     * @param  int  $expiresIn  アクセストークンの有効期限（秒）
     *
     * @throws \InvalidArgumentException 値が不正な場合
     */
    public function __construct(
        private readonly string $accessToken,
        private readonly string $refreshToken,
        private readonly int $expiresIn,
    ) {
        if ($accessToken === '') {
            throw new \InvalidArgumentException('アクセストークンは必須です');
        }

        if ($refreshToken === '') {
            throw new \InvalidArgumentException('リフレッシュトークンは必須です');
        }

        if ($expiresIn <= 0) {
            throw new \InvalidArgumentException("有効期限は1秒以上である必要があります: {$expiresIn}");
        }
    }

    /**
     * アクセストークン取得
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * リフレッシュトークン取得
     */
    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    /**
     * 有効期限（秒）取得
     */
    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    /**
     * 値が等しいか
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->accessToken, $other->accessToken)
            && hash_equals($this->refreshToken, $other->refreshToken)
            && $this->expiresIn === $other->expiresIn;
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
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
