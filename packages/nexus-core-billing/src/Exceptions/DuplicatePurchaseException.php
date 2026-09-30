<?php

namespace NexusBilling\Exceptions;

/**
 * 重複購入の例外
 *
 * 同じレシートで既に購入処理が完了している場合にスローされる
 */
class DuplicatePurchaseException extends ReceiptVerificationException
{
    public function __construct(string $message = 'Duplicate purchase detected', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
