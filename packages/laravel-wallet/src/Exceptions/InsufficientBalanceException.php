<?php

namespace LaravelWallet\Exceptions;

/**
 * 残高不足例外
 * 
 * 通貨を消費しようとした際に残高が不足している場合にスローされる
 */
class InsufficientBalanceException extends WalletException
{
    public function __construct(
        string $currencyId,
        int $required,
        int $available,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $message = "Insufficient balance for currency '{$currencyId}': required {$required}, available {$available}";
        parent::__construct($message, $code, $previous);
    }
}
