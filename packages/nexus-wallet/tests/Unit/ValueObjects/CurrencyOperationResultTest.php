<?php

namespace NexusWallet\Tests\Unit\ValueObjects;

use NexusWallet\ValueObjects\CurrencyOperationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CurrencyOperationResultTest extends TestCase
{
    #[Test]
    public function test_construct_and_getters()
    {
        $result = new CurrencyOperationResult(
            freeAmount: 100,
            paidAmount: 50,
            currentBalance: 1000,
        );

        $this->assertSame(100, $result->getFreeAmount());
        $this->assertSame(50, $result->getPaidAmount());
        $this->assertSame(150, $result->getTotalAmount());
        $this->assertSame(1000, $result->getCurrentBalance());
    }

    #[Test]
    public function test_total_amount_is_derived_from_breakdown()
    {
        $result = new CurrencyOperationResult(freeAmount: 30, paidAmount: 70, currentBalance: 0);

        $this->assertSame(100, $result->getTotalAmount());
    }

    #[Test]
    public function test_has_changed()
    {
        $moved = new CurrencyOperationResult(freeAmount: 1, paidAmount: 0, currentBalance: 10);
        $untouched = new CurrencyOperationResult(freeAmount: 0, paidAmount: 0, currentBalance: 10);

        $this->assertTrue($moved->hasChanged());
        $this->assertFalse($untouched->hasChanged());
    }

    #[Test]
    public function test_negative_values_are_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);

        new CurrencyOperationResult(freeAmount: -1, paidAmount: 0, currentBalance: 0);
    }

    #[Test]
    public function test_negative_balance_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);

        new CurrencyOperationResult(freeAmount: 0, paidAmount: 0, currentBalance: -1);
    }

    #[Test]
    public function test_equals_compares_by_value()
    {
        $a = new CurrencyOperationResult(freeAmount: 10, paidAmount: 5, currentBalance: 100);
        $b = new CurrencyOperationResult(freeAmount: 10, paidAmount: 5, currentBalance: 100);
        $c = new CurrencyOperationResult(freeAmount: 10, paidAmount: 5, currentBalance: 101);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    #[Test]
    public function test_to_array()
    {
        $result = new CurrencyOperationResult(freeAmount: 100, paidAmount: 50, currentBalance: 1000);

        $array = $result->toArray();

        $this->assertSame(100, $array['free_amount']);
        $this->assertSame(50, $array['paid_amount']);
        $this->assertSame(150, $array['total_amount']);
        $this->assertSame(1000, $array['current_balance']);
    }

    #[Test]
    public function test_to_json()
    {
        $result = new CurrencyOperationResult(freeAmount: 100, paidAmount: 50, currentBalance: 1000);

        $decoded = json_decode($result->toJson(), true);

        $this->assertSame(150, $decoded['total_amount']);
    }
}
