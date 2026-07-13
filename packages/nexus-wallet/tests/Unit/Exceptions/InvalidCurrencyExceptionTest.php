<?php

namespace LaravelWallet\Tests\Unit\Exceptions;

use LaravelWallet\Exceptions\InvalidCurrencyException;
use LaravelWallet\Exceptions\WalletException;
use PHPUnit\Framework\TestCase;

class InvalidCurrencyExceptionTest extends TestCase
{
    public function test_exception_message()
    {
        $exception = new InvalidCurrencyException('invalid_currency');

        $expectedMessage = "Invalid currency ID: invalid_currency";
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function test_exception_extends_wallet_exception()
    {
        $exception = new InvalidCurrencyException('invalid_currency');

        $this->assertInstanceOf(WalletException::class, $exception);
    }

    public function test_exception_code_and_previous()
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidCurrencyException('invalid_currency', 2001, $previous);

        $this->assertSame(2001, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
