<?php

namespace LaravelWallet\Tests\Unit\DTOs;

use LaravelWallet\DTOs\CurrencyOperationResultDto;
use PHPUnit\Framework\TestCase;

class CurrencyOperationResultDtoTest extends TestCase
{
    public function test_construct_with_all_parameters()
    {
        $result = new CurrencyOperationResultDto(
            freeAmount: 100,
            paidAmount: 50,
            totalAmount: 150,
            currentBalance: 1000
        );

        $this->assertSame(100, $result->freeAmount);
        $this->assertSame(50, $result->paidAmount);
        $this->assertSame(150, $result->totalAmount);
        $this->assertSame(1000, $result->currentBalance);
    }

    public function test_to_array()
    {
        $result = new CurrencyOperationResultDto(
            freeAmount: 100,
            paidAmount: 50,
            totalAmount: 150,
            currentBalance: 1000
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('free_amount', $array);
        $this->assertArrayHasKey('paid_amount', $array);
        $this->assertArrayHasKey('total_amount', $array);
        $this->assertArrayHasKey('current_balance', $array);
        $this->assertSame(100, $array['free_amount']);
        $this->assertSame(50, $array['paid_amount']);
        $this->assertSame(150, $array['total_amount']);
        $this->assertSame(1000, $array['current_balance']);
    }

    public function test_to_json()
    {
        $result = new CurrencyOperationResultDto(
            freeAmount: 100,
            paidAmount: 50,
            totalAmount: 150,
            currentBalance: 1000
        );

        $json = $result->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame(100, $decoded['free_amount']);
        $this->assertSame(50, $decoded['paid_amount']);
        $this->assertSame(150, $decoded['total_amount']);
        $this->assertSame(1000, $decoded['current_balance']);
    }
}
