<?php

namespace LaravelWallet\Tests\Unit\ValueObjects;

use LaravelWallet\ValueObjects\CurrencyBalance;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CurrencyBalanceTest extends TestCase
{
    #[Test]
    public function test_construct_with_all_parameters()
    {
        $expireAt = '2026-08-12 00:00:00';
        $balance = new CurrencyBalance(
            freeAmount: 1000,
            paidAmount: 500,
            expireAt: $expireAt
        );

        $this->assertSame(1000, $balance->getFreeAmount());
        $this->assertSame(500, $balance->getPaidAmount());
        $this->assertSame(1500, $balance->getTotalAmount());
        $this->assertSame($expireAt, $balance->getExpireAt());
    }

    #[Test]
    public function test_construct_without_expire_at()
    {
        $balance = new CurrencyBalance(freeAmount: 2000, paidAmount: 0);

        $this->assertSame(2000, $balance->getFreeAmount());
        $this->assertSame(0, $balance->getPaidAmount());
        $this->assertSame(2000, $balance->getTotalAmount());
        $this->assertNull($balance->getExpireAt());
    }

    #[Test]
    public function test_total_amount_is_derived_from_breakdown()
    {
        $balance = new CurrencyBalance(freeAmount: 300, paidAmount: 700);

        $this->assertSame(1000, $balance->getTotalAmount());
    }

    #[Test]
    public function test_zero_creates_empty_balance()
    {
        $balance = CurrencyBalance::zero();

        $this->assertSame(0, $balance->getTotalAmount());
        $this->assertTrue($balance->isEmpty());
    }

    #[Test]
    public function test_negative_free_amount_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);

        new CurrencyBalance(freeAmount: -1, paidAmount: 0);
    }

    #[Test]
    public function test_negative_paid_amount_is_rejected()
    {
        $this->expectException(\InvalidArgumentException::class);

        new CurrencyBalance(freeAmount: 0, paidAmount: -1);
    }

    #[Test]
    public function test_can_afford()
    {
        $balance = new CurrencyBalance(freeAmount: 100, paidAmount: 50);

        $this->assertTrue($balance->canAfford(150));
        $this->assertFalse($balance->canAfford(151));
    }

    #[Test]
    public function test_add_returns_new_instance()
    {
        $balance = new CurrencyBalance(freeAmount: 100, paidAmount: 50);
        $added = $balance->add(freeAmount: 10, paidAmount: 20);

        // 元のインスタンスは変化しない（不変）
        $this->assertSame(150, $balance->getTotalAmount());
        $this->assertSame(110, $added->getFreeAmount());
        $this->assertSame(70, $added->getPaidAmount());
    }

    #[Test]
    public function test_subtract_paid_first_consumes_paid_before_free()
    {
        $balance = new CurrencyBalance(freeAmount: 100, paidAmount: 50);

        // 有償50を使い切り、残り10を無償から消費
        $result = $balance->subtractPaidFirst(60);

        $this->assertSame(90, $result->getFreeAmount());
        $this->assertSame(0, $result->getPaidAmount());
    }

    #[Test]
    public function test_subtract_paid_first_keeps_free_when_paid_is_enough()
    {
        $balance = new CurrencyBalance(freeAmount: 100, paidAmount: 50);

        $result = $balance->subtractPaidFirst(30);

        $this->assertSame(100, $result->getFreeAmount());
        $this->assertSame(20, $result->getPaidAmount());
    }

    #[Test]
    public function test_subtract_paid_first_rejects_insufficient_balance()
    {
        $balance = new CurrencyBalance(freeAmount: 10, paidAmount: 5);

        $this->expectException(\InvalidArgumentException::class);

        $balance->subtractPaidFirst(16);
    }

    #[Test]
    public function test_equals_compares_by_value()
    {
        $a = new CurrencyBalance(freeAmount: 100, paidAmount: 50);
        $b = new CurrencyBalance(freeAmount: 100, paidAmount: 50);
        $c = new CurrencyBalance(freeAmount: 100, paidAmount: 51);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    #[Test]
    public function test_to_array()
    {
        $expireAt = '2026-08-12 00:00:00';
        $balance = new CurrencyBalance(
            freeAmount: 1000,
            paidAmount: 500,
            expireAt: $expireAt
        );

        $array = $balance->toArray();

        $this->assertSame(1000, $array['free_amount']);
        $this->assertSame(500, $array['paid_amount']);
        $this->assertSame(1500, $array['total_amount']);
        $this->assertSame($expireAt, $array['expire_at']);
    }

    #[Test]
    public function test_to_array_with_null_expire_at()
    {
        $balance = new CurrencyBalance(freeAmount: 1000, paidAmount: 500);

        $this->assertNull($balance->toArray()['expire_at']);
    }

    #[Test]
    public function test_to_json()
    {
        $balance = new CurrencyBalance(
            freeAmount: 1000,
            paidAmount: 500,
            expireAt: '2026-08-12 00:00:00'
        );

        $decoded = json_decode($balance->toJson(), true);

        $this->assertSame(1000, $decoded['free_amount']);
        $this->assertSame(500, $decoded['paid_amount']);
        $this->assertSame(1500, $decoded['total_amount']);
    }
}
