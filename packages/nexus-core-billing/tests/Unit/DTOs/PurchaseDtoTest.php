<?php

namespace NexusBilling\Tests\Unit\DTOs;

use NexusBilling\DTOs\PurchaseDto;
use PHPUnit\Framework\TestCase;

class PurchaseDtoTest extends TestCase
{
    public function test_construct_with_all_parameters()
    {
        $purchaseDate = '2026-07-13 10:00:00';
        $purchaseInfo = new PurchaseDto(
            playerId: 123,
            billingPlatform: 'google_play',
            productId: 'com.example.product1',
            transactionId: 'txn_123456',
            quantity: 1,
            purchaseDate: $purchaseDate,
            price: 9.99,
            currency: 'USD'
        );

        $this->assertSame(123, $purchaseInfo->playerId);
        $this->assertSame('google_play', $purchaseInfo->billingPlatform);
        $this->assertSame('com.example.product1', $purchaseInfo->productId);
        $this->assertSame('txn_123456', $purchaseInfo->transactionId);
        $this->assertSame(1, $purchaseInfo->quantity);
        $this->assertSame($purchaseDate, $purchaseInfo->purchaseDate);
        $this->assertSame(9.99, $purchaseInfo->price);
        $this->assertSame('USD', $purchaseInfo->currency);
    }

    public function test_construct_without_optional_parameters()
    {
        $purchaseDate = '2026-07-13 10:00:00';
        $purchaseInfo = new PurchaseDto(
            playerId: 456,
            billingPlatform: 'app_store',
            productId: 'com.example.product2',
            transactionId: 'txn_789012',
            quantity: 2,
            purchaseDate: $purchaseDate
        );

        $this->assertSame(456, $purchaseInfo->playerId);
        $this->assertNull($purchaseInfo->price);
        $this->assertNull($purchaseInfo->currency);
    }

    public function test_to_array()
    {
        $purchaseDate = '2026-07-13 10:00:00';
        $purchaseInfo = new PurchaseDto(
            playerId: 123,
            billingPlatform: 'google_play',
            productId: 'com.example.product1',
            transactionId: 'txn_123456',
            quantity: 1,
            purchaseDate: $purchaseDate,
            price: 9.99,
            currency: 'USD'
        );

        $array = $purchaseInfo->toArray();

        $this->assertIsArray($array);
        $this->assertSame(123, $array['player_id']);
        $this->assertSame('google_play', $array['billing_platform']);
        $this->assertSame('com.example.product1', $array['product_id']);
        $this->assertSame('txn_123456', $array['transaction_id']);
        $this->assertSame(1, $array['quantity']);
        $this->assertSame($purchaseDate, $array['purchase_date']);
        $this->assertSame(9.99, $array['price']);
        $this->assertSame('USD', $array['currency']);
    }

    public function test_to_json()
    {
        $purchaseDate = '2026-07-13 10:00:00';
        $purchaseInfo = new PurchaseDto(
            playerId: 123,
            billingPlatform: 'google_play',
            productId: 'com.example.product1',
            transactionId: 'txn_123456',
            quantity: 1,
            purchaseDate: $purchaseDate,
            price: 9.99,
            currency: 'USD'
        );

        $json = $purchaseInfo->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame(123, $decoded['player_id']);
        $this->assertSame('google_play', $decoded['billing_platform']);
        $this->assertSame(9.99, $decoded['price']);
    }
}
