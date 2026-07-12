<?php

namespace LaravelWallet\Exceptions;

use Exception;

/**
 * Wallet例外の基底クラス
 */
class WalletException extends Exception
{
    public function __construct(string $message = "Wallet operation failed", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
