<?php

namespace LaravelWallet\Exceptions;

/**
 * 無効な通貨例外
 * 
 * 存在しない通貨IDが指定された場合にスローされる
 */
class InvalidCurrencyException extends WalletException
{
    public function __construct(string $currencyId, int $code = 0, ?\Throwable $previous = null)
    {
        $message = "Invalid currency ID: {$currencyId}";
        parent::__construct($message, $code, $previous);
    }
}
