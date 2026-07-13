<?php

namespace LaravelMobileBilling\Tests\Unit\Exceptions;

use LaravelMobileBilling\Exceptions\PlatformApiException;
use LaravelMobileBilling\Exceptions\ReceiptVerificationException;
use PHPUnit\Framework\TestCase;

class PlatformApiExceptionTest extends TestCase
{
    public function test_default_message()
    {
        $exception = new PlatformApiException();

        $this->assertSame("Platform API error", $exception->getMessage());
    }

    public function test_custom_message()
    {
        $exception = new PlatformApiException("AppStore API returned 500 error");

        $this->assertSame("AppStore API returned 500 error", $exception->getMessage());
    }

    public function test_exception_extends_receipt_verification_exception()
    {
        $exception = new PlatformApiException();

        $this->assertInstanceOf(ReceiptVerificationException::class, $exception);
    }

    public function test_exception_code_and_previous()
    {
        $previous = new \Exception('Previous exception');
        $exception = new PlatformApiException("API timeout error", 4001, $previous);

        $this->assertSame(4001, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
