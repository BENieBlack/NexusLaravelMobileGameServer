<?php

namespace NexusBilling\Tests\Unit\Exceptions;

use NexusBilling\Exceptions\InvalidReceiptException;
use NexusBilling\Exceptions\ReceiptVerificationException;
use PHPUnit\Framework\TestCase;

class InvalidReceiptExceptionTest extends TestCase
{
    public function test_default_message()
    {
        $exception = new InvalidReceiptException;

        $this->assertSame('Invalid receipt', $exception->getMessage());
    }

    public function test_custom_message()
    {
        $exception = new InvalidReceiptException('Receipt signature is invalid');

        $this->assertSame('Receipt signature is invalid', $exception->getMessage());
    }

    public function test_exception_extends_receipt_verification_exception()
    {
        $exception = new InvalidReceiptException;

        $this->assertInstanceOf(ReceiptVerificationException::class, $exception);
    }

    public function test_exception_code_and_previous()
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidReceiptException('Invalid receipt error', 3001, $previous);

        $this->assertSame(3001, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
