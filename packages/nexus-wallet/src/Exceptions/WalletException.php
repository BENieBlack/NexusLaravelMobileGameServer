<?php

namespace NexusWallet\Exceptions;

use Exception;

/**
 * Wallet例外の基底クラス
 *
 * パッケージ層（nexus-wallet）の例外基底クラス
 * エラーコードは4桁（1000-1099）を使用
 */
class WalletException extends Exception
{
    public function __construct(
        string $message = 'Wallet operation failed',
        int $code = WalletErrorCode::WALLET_NOT_FOUND,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
