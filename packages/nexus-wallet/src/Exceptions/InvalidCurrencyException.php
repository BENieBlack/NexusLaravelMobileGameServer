<?php

namespace NexusWallet\Exceptions;

/**
 * 無効な通貨例外
 *
 * 存在しない通貨IDが指定された場合にスローされる
 * エラーコード: 1002 (WalletErrorCode::INVALID_CURRENCY)
 */
class InvalidCurrencyException extends WalletException
{
    public function __construct(string $currencyId, ?\Throwable $previous = null)
    {
        $message = "Invalid currency ID: {$currencyId}";
        parent::__construct($message, WalletErrorCode::INVALID_CURRENCY, $previous);
    }
}
