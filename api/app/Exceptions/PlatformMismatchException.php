<?php

namespace App\Exceptions;

/**
 * PlatformMismatchException
 *
 * リクエスト body の platform と X-Platform ヘッダーが一致しないときの例外。
 * エラーコードは X-Platform の欠落・不正（ResolvePlatform）と同じ INVALID_PLATFORM（99003）で、
 * メッセージで区別する。ClientImplementationError なので Slack へは通知しない
 * （設計 free-diamond-item の api.md 5.2 節・U-Q3）。
 */
class PlatformMismatchException extends GameException implements ClientImplementationError
{
    /**
     * body の platform が X-Platform と一致しない
     */
    public static function bodyAndHeader(): self
    {
        return new self(
            GameErrorCode::INVALID_PLATFORM,
            'platform in body does not match X-Platform'
        );
    }
}
