<?php

namespace LaravelMobileBilling\Tests\Unit\DTOs;

use LaravelMobileBilling\DTOs\ReceiptData;
use PHPUnit\Framework\TestCase;

class ReceiptDataTest extends TestCase
{
    public function test_construct_with_app_store_receipt()
    {
        $receiptData = new ReceiptData(
            playerId: 123,
            billingPlatform: 'app_store',
            receipt: 'base64_encoded_receipt_data',
            transactionId: 'txn_123456'
        );

        $this->assertSame(123, $receiptData->playerId);
        $this->assertSame('app_store', $receiptData->billingPlatform);
        $this->assertSame('base64_encoded_receipt_data', $receiptData->receipt);
        $this->assertNull($receiptData->purchaseToken);
        $this->assertNull($receiptData->productId);
        $this->assertSame('txn_123456', $receiptData->transactionId);
    }

    public function test_construct_with_google_play_token()
    {
        $receiptData = new ReceiptData(
            playerId: 456,
            billingPlatform: 'google_play',
            purchaseToken: 'google_purchase_token_xyz',
            productId: 'com.example.product1',
            transactionId: 'txn_789012'
        );

        $this->assertSame(456, $receiptData->playerId);
        $this->assertSame('google_play', $receiptData->billingPlatform);
        $this->assertNull($receiptData->receipt);
        $this->assertSame('google_purchase_token_xyz', $receiptData->purchaseToken);
        $this->assertSame('com.example.product1', $receiptData->productId);
        $this->assertSame('txn_789012', $receiptData->transactionId);
    }

    public function test_to_array()
    {
        $receiptData = new ReceiptData(
            playerId: 123,
            billingPlatform: 'app_store',
            receipt: 'base64_encoded_receipt_data',
            transactionId: 'txn_123456'
        );

        $array = $receiptData->toArray();

        $this->assertIsArray($array);
        $this->assertSame(123, $array['player_id']);
        $this->assertSame('app_store', $array['billing_platform']);
        $this->assertSame('base64_encoded_receipt_data', $array['receipt']);
        $this->assertNull($array['purchase_token']);
        $this->assertNull($array['product_id']);
        $this->assertSame('txn_123456', $array['transaction_id']);
    }

    public function test_to_json()
    {
        $receiptData = new ReceiptData(
            playerId: 456,
            billingPlatform: 'google_play',
            purchaseToken: 'google_purchase_token_xyz',
            productId: 'com.example.product1'
        );

        $json = $receiptData->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame(456, $decoded['player_id']);
        $this->assertSame('google_play', $decoded['billing_platform']);
        $this->assertSame('google_purchase_token_xyz', $decoded['purchase_token']);
        $this->assertSame('com.example.product1', $decoded['product_id']);
    }
}
