<?php

namespace App\Domain\Billing\Exceptions;

/**
 * プラットフォームAPI通信エラーの例外
 * 
 * AppStore、GooglePlay等の外部APIとの通信でエラーが発生した場合にスローされる
 */
class PlatformApiException extends ReceiptVerificationException
{
    public function __construct(string $message = "Platform API error", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
