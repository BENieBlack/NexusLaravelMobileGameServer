<?php

namespace NexusAuth\Exceptions;

use RuntimeException;

/**
 * ExternalIdTokenException
 *
 * 外部アカウントのIDトークン検証の例外
 *
 * パッケージはゲーム固有のエラーコード体系を知らないため、種類だけを持つ。
 * クライアントへ返すコードへの変換はアプリケーション層で行う。
 */
class ExternalIdTokenException extends RuntimeException
{
    public const CODE_UNSUPPORTED_PROVIDER = 'UNSUPPORTED_PROVIDER';

    public const CODE_INVALID_TOKEN = 'INVALID_TOKEN';

    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * 設定に無い、または発行先（aud）が設定されていない種類
     */
    public static function unsupportedProvider(string $provider): self
    {
        return new self(
            self::CODE_UNSUPPORTED_PROVIDER,
            "外部アカウントの種類が未対応か、設定されていません: provider={$provider}",
        );
    }

    /**
     * 署名・有効期限・発行者・発行先・subのいずれかが不正
     */
    public static function invalidToken(string $provider, string $reason): self
    {
        return new self(
            self::CODE_INVALID_TOKEN,
            "IDトークンを検証できません: provider={$provider}, reason={$reason}",
        );
    }
}
