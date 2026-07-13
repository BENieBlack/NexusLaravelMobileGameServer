<?php

namespace LaravelMobileBilling\Tests\Unit\DTOs;

use LaravelMobileBilling\DTOs\VerificationResult;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class VerificationResultTest extends TestCase
{
    public function test_construct_with_all_parameters()
    {
        $purchaseDate = CarbonImmutable::parse('2026-07-13 10:00:00');
        $rawResponse = ['status' => 'success', 'data' => ['test' => 'value']];
        
        $result = new VerificationResult(
            isValid: true,
            transactionId: 'txn_123456',
            productId: 'com.example.product1',
            purchaseDate: $purchaseDate,
            quantity: 1,
            originalTransactionId: 'original_txn_123456',
            rawResponse: $rawResponse
        );

        $this->assertTrue($result->isValid);
        $this->assertSame('txn_123456', $result->transactionId);
        $this->assertSame('com.example.product1', $result->productId);
        $this->assertSame($purchaseDate, $result->purchaseDate);
        $this->assertSame(1, $result->quantity);
        $this->assertSame('original_txn_123456', $result->originalTransactionId);
        $this->assertSame($rawResponse, $result->rawResponse);
    }

    public function test_construct_with_invalid_result()
    {
        $purchaseDate = CarbonImmutable::parse('2026-07-13 10:00:00');
        
        $result = new VerificationResult(
            isValid: false,
            transactionId: 'txn_789012',
            productId: 'com.example.product2',
            purchaseDate: $purchaseDate,
            quantity: 1,
            originalTransactionId: 'original_txn_789012',
            rawResponse: ['status' => 'failed']
        );

        $this->assertFalse($result->isValid);
    }

    public function test_to_array()
    {
        $purchaseDate = CarbonImmutable::parse('2026-07-13 10:00:00');
        $rawResponse = ['status' => 'success'];
        
        $result = new VerificationResult(
            isValid: true,
            transactionId: 'txn_123456',
            productId: 'com.example.product1',
            purchaseDate: $purchaseDate,
            quantity: 2,
            originalTransactionId: 'original_txn_123456',
            rawResponse: $rawResponse
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertTrue($array['is_valid']);
        $this->assertSame('txn_123456', $array['transaction_id']);
        $this->assertSame('com.example.product1', $array['product_id']);
        $this->assertSame($purchaseDate->toIso8601String(), $array['purchase_date']);
        $this->assertSame(2, $array['quantity']);
        $this->assertSame('original_txn_123456', $array['original_transaction_id']);
        $this->assertSame($rawResponse, $array['raw_response']);
    }

    public function test_to_json()
    {
        $purchaseDate = CarbonImmutable::parse('2026-07-13 10:00:00');
        
        $result = new VerificationResult(
            isValid: true,
            transactionId: 'txn_123456',
            productId: 'com.example.product1',
            purchaseDate: $purchaseDate,
            quantity: 1,
            originalTransactionId: 'original_txn_123456',
            rawResponse: ['status' => 'success']
        );

        $json = $result->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['is_valid']);
        $this->assertSame('txn_123456', $decoded['transaction_id']);
        $this->assertSame('com.example.product1', $decoded['product_id']);
    }
}
