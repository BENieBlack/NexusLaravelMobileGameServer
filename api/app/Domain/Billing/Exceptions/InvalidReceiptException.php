<?php

namespace App\Domain\Billing\Exceptions;

/**
 * 無効なレシートの例外
 * 
 * レシートの内容が不正、または検証に失敗した場合にスローされる
 */
class InvalidReceiptException extends ReceiptVerificationException
{
    public function __construct(string $message = "Invalid receipt", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
