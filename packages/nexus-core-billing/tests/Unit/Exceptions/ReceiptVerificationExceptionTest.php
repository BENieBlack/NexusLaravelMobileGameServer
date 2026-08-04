<?php

namespace NexusBilling\Tests\Unit\Exceptions;

use NexusBilling\Exceptions\ReceiptVerificationException;
use PHPUnit\Framework\TestCase;

class ReceiptVerificationExceptionTest extends TestCase
{
    public function test_default_message()
    {
        $exception = new ReceiptVerificationException();

        $this->assertSame("Receipt verification failed", $exception->getMessage());
    }

    public function test_custom_message()
    {
        $exception = new ReceiptVerificationException("Custom error message");

        $this->assertSame("Custom error message", $exception->getMessage());
    }

    public function test_exception_code_and_previous()
    {
        $previous = new \Exception('Previous exception');
        $exception = new ReceiptVerificationException("Test error", 1001, $previous);

        $this->assertSame(1001, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_exception_is_throwable()
    {
        $exception = new ReceiptVerificationException();

        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }
}
