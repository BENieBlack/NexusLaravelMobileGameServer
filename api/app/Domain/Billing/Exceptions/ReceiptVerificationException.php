<?php

namespace App\Domain\Billing\Exceptions;

use Exception;

/**
 * レシート検証失敗時の例外
 * 
 * レシート検証処理で何らかのエラーが発生した場合にスローされる基底例外
 */
class ReceiptVerificationException extends Exception
{
    public function __construct(string $message = "Receipt verification failed", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
