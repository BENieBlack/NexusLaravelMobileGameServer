<?php

namespace NexusBilling\Tests\Unit\DTOs;

use NexusBilling\DTOs\ReceiptDto;
use PHPUnit\Framework\TestCase;

class ReceiptDtoTest extends TestCase
{
    public function test_construct_with_app_store_receipt()
    {
        $receiptDto = new ReceiptDto(
            playerId: 123,
            billingPlatform: 'app_store',
            receipt: 'base64_encoded_receipt_data',
            transactionId: 'txn_123456'
        );

        $this->assertSame(123, $receiptDto->getPlayerId());
        $this->assertSame('app_store', $receiptDto->getBillingPlatform());
        $this->assertSame('base64_encoded_receipt_data', $receiptDto->getReceipt());
        $this->assertNull($receiptDto->getPurchaseToken());
        $this->assertNull($receiptDto->getProductId());
        $this->assertSame('txn_123456', $receiptDto->getTransactionId());
    }

    public function test_construct_with_google_play_token()
    {
        $receiptDto = new ReceiptDto(
            playerId: 456,
            billingPlatform: 'google_play',
            purchaseToken: 'google_purchase_token_xyz',
            productId: 'com.example.product1',
            transactionId: 'txn_789012'
        );

        $this->assertSame(456, $receiptDto->getPlayerId());
        $this->assertSame('google_play', $receiptDto->getBillingPlatform());
        $this->assertNull($receiptDto->getReceipt());
        $this->assertSame('google_purchase_token_xyz', $receiptDto->getPurchaseToken());
        $this->assertSame('com.example.product1', $receiptDto->getProductId());
        $this->assertSame('txn_789012', $receiptDto->getTransactionId());
    }

    public function test_to_array()
    {
        $receiptDto = new ReceiptDto(
            playerId: 123,
            billingPlatform: 'app_store',
            receipt: 'base64_encoded_receipt_data',
            transactionId: 'txn_123456'
        );

        $array = $receiptDto->toArray();

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
        $receiptDto = new ReceiptDto(
            playerId: 456,
            billingPlatform: 'google_play',
            purchaseToken: 'google_purchase_token_xyz',
            productId: 'com.example.product1'
        );

        $json = $receiptDto->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame(456, $decoded['player_id']);
        $this->assertSame('google_play', $decoded['billing_platform']);
        $this->assertSame('google_purchase_token_xyz', $decoded['purchase_token']);
        $this->assertSame('com.example.product1', $decoded['product_id']);
    }
}
