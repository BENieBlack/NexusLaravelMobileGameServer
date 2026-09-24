<?php

namespace NexusBilling\Tests\Unit\DataTransferObjects;

use NexusBilling\DataTransferObjects\Verification;
use PHPUnit\Framework\TestCase;

class VerificationDtoTest extends TestCase
{
    public function test_construct_with_all_parameters()
    {
        $purchaseDate = '2026-07-13 10:00:00';
        $rawResponse = ['status' => 'success', 'data' => ['test' => 'value']];

        $result = new Verification(
            isValid: true,
            transactionId: 'txn_123456',
            productId: 'com.example.product1',
            purchaseDate: $purchaseDate,
            quantity: 1,
            originalTransactionId: 'original_txn_123456',
            rawResponse: $rawResponse
        );

        $this->assertTrue($result->getIsValid());
        $this->assertSame('txn_123456', $result->getTransactionId());
        $this->assertSame('com.example.product1', $result->getProductId());
        $this->assertSame($purchaseDate, $result->getPurchaseDate());
        $this->assertSame(1, $result->getQuantity());
        $this->assertSame('original_txn_123456', $result->getOriginalTransactionId());
        $this->assertSame($rawResponse, $result->getRawResponse());
    }

    public function test_construct_with_invalid_result()
    {
        $purchaseDate = '2026-07-13 10:00:00';

        $result = new Verification(
            isValid: false,
            transactionId: 'txn_789012',
            productId: 'com.example.product2',
            purchaseDate: $purchaseDate,
            quantity: 1,
            originalTransactionId: 'original_txn_789012',
            rawResponse: ['status' => 'failed']
        );

        $this->assertFalse($result->getIsValid());
    }

    public function test_to_array()
    {
        $purchaseDate = '2026-07-13 10:00:00';
        $rawResponse = ['status' => 'success'];

        $result = new Verification(
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
        $this->assertSame($purchaseDate, $array['purchase_date']);
        $this->assertSame(2, $array['quantity']);
        $this->assertSame('original_txn_123456', $array['original_transaction_id']);
        $this->assertSame($rawResponse, $array['raw_response']);
    }

    public function test_to_json()
    {
        $purchaseDate = '2026-07-13 10:00:00';

        $result = new Verification(
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
