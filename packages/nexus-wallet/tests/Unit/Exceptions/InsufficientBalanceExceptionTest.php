<?php

namespace NexusWallet\Tests\Unit\Exceptions;

use NexusWallet\Exceptions\InsufficientBalanceException;
use NexusWallet\Exceptions\WalletErrorCode;
use NexusWallet\Exceptions\WalletException;
use PHPUnit\Framework\TestCase;

class InsufficientBalanceExceptionTest extends TestCase
{
    public function test_exception_message()
    {
        $exception = new InsufficientBalanceException('gold', 1000, 500);

        $expectedMessage = "Insufficient balance for currency 'gold': required 1000, available 500";
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function test_exception_extends_wallet_exception()
    {
        $exception = new InsufficientBalanceException('gold', 1000, 500);

        $this->assertInstanceOf(WalletException::class, $exception);
    }

    public function test_exception_code_and_previous()
    {
        $previous = new \Exception('Previous exception');
        $exception = new InsufficientBalanceException('event_coin', 2000, 1500, $previous);

        $this->assertSame(WalletErrorCode::INSUFFICIENT_BALANCE, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
