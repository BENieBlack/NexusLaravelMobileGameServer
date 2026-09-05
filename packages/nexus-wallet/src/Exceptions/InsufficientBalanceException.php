<?php

namespace NexusWallet\Exceptions;

/**
 * 残高不足例外
 *
 * 通貨を消費しようとした際に残高が不足している場合にスローされる
 * エラーコード: 1001 (WalletErrorCode::INSUFFICIENT_BALANCE)
 */
class InsufficientBalanceException extends WalletException
{
    public function __construct(
        string $currencyId,
        int $required,
        int $available,
        ?\Throwable $previous = null
    ) {
        $message = "Insufficient balance for currency '{$currencyId}': required {$required}, available {$available}";
        parent::__construct($message, WalletErrorCode::INSUFFICIENT_BALANCE, $previous);
    }
}
