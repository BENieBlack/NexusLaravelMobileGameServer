<?php

namespace NexusWallet\Tests\Unit\Exceptions;

use NexusWallet\Exceptions\WalletErrorCode;
use NexusWallet\Exceptions\WalletException;
use PHPUnit\Framework\TestCase;

class WalletExceptionTest extends TestCase
{
    public function test_default_message()
    {
        $exception = new WalletException();

        $this->assertSame("Wallet operation failed", $exception->getMessage());
        $this->assertSame(WalletErrorCode::WALLET_NOT_FOUND, $exception->getCode());
    }

    public function test_custom_message()
    {
        $exception = new WalletException("Custom error message");

        $this->assertSame("Custom error message", $exception->getMessage());
    }

    public function test_exception_code_and_previous()
    {
        $previous = new \Exception('Previous exception');
        $exception = new WalletException("Test error", 9999, $previous);

        $this->assertSame(9999, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_exception_is_throwable()
    {
        $exception = new WalletException();

        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }
}
