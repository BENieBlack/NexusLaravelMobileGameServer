<?php

namespace NexusBilling\Tests\Unit\Exceptions;

use NexusBilling\Exceptions\DuplicatePurchaseException;
use NexusBilling\Exceptions\ReceiptVerificationException;
use PHPUnit\Framework\TestCase;

class DuplicatePurchaseExceptionTest extends TestCase
{
    public function test_default_message()
    {
        $exception = new DuplicatePurchaseException();

        $this->assertSame("Duplicate purchase detected", $exception->getMessage());
    }

    public function test_custom_message()
    {
        $exception = new DuplicatePurchaseException("Custom duplicate purchase error");

        $this->assertSame("Custom duplicate purchase error", $exception->getMessage());
    }

    public function test_exception_extends_receipt_verification_exception()
    {
        $exception = new DuplicatePurchaseException();

        $this->assertInstanceOf(ReceiptVerificationException::class, $exception);
    }

    public function test_exception_code_and_previous()
    {
        $previous = new \Exception('Previous exception');
        $exception = new DuplicatePurchaseException("Duplicate purchase error", 2001, $previous);

        $this->assertSame(2001, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
