<?php

namespace LaravelWallet\Tests\Unit\DTOs;

use LaravelWallet\DTOs\CurrencyBalanceDto;
use PHPUnit\Framework\TestCase;

class CurrencyBalanceDtoTest extends TestCase
{
    public function test_construct_with_all_parameters()
    {
        $expireAt = '2026-08-12 00:00:00';
        $balance = new CurrencyBalanceDto(
            freeAmount: 1000,
            paidAmount: 500,
            totalAmount: 1500,
            expireAt: $expireAt
        );

        $this->assertSame(1000, $balance->freeAmount);
        $this->assertSame(500, $balance->paidAmount);
        $this->assertSame(1500, $balance->totalAmount);
        $this->assertSame($expireAt, $balance->expireAt);
    }

    public function test_construct_without_expire_at()
    {
        $balance = new CurrencyBalanceDto(
            freeAmount: 2000,
            paidAmount: 0,
            totalAmount: 2000
        );

        $this->assertSame(2000, $balance->freeAmount);
        $this->assertSame(0, $balance->paidAmount);
        $this->assertSame(2000, $balance->totalAmount);
        $this->assertNull($balance->expireAt);
    }

    public function test_to_array()
    {
        $expireAt = '2026-08-12 00:00:00';
        $balance = new CurrencyBalanceDto(
            freeAmount: 1000,
            paidAmount: 500,
            totalAmount: 1500,
            expireAt: $expireAt
        );

        $array = $balance->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('free_amount', $array);
        $this->assertArrayHasKey('paid_amount', $array);
        $this->assertArrayHasKey('total_amount', $array);
        $this->assertArrayHasKey('expire_at', $array);
        $this->assertSame(1000, $array['free_amount']);
        $this->assertSame(500, $array['paid_amount']);
        $this->assertSame(1500, $array['total_amount']);
        $this->assertSame($expireAt, $array['expire_at']);
    }

    public function test_to_array_with_null_expire_at()
    {
        $balance = new CurrencyBalanceDto(
            freeAmount: 1000,
            paidAmount: 500,
            totalAmount: 1500
        );

        $array = $balance->toArray();

        $this->assertNull($array['expire_at']);
    }

    public function test_to_json()
    {
        $expireAt = '2026-08-12 00:00:00';
        $balance = new CurrencyBalanceDto(
            freeAmount: 1000,
            paidAmount: 500,
            totalAmount: 1500,
            expireAt: $expireAt
        );

        $json = $balance->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame(1000, $decoded['free_amount']);
        $this->assertSame(500, $decoded['paid_amount']);
        $this->assertSame(1500, $decoded['total_amount']);
        $this->assertSame($expireAt, $decoded['expire_at']);
    }
}
