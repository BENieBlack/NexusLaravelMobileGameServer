<?php

namespace NexusBilling\Tests\Unit\DataTransferObjects;

use NexusBilling\DataTransferObjects\Receipt;
use PHPUnit\Framework\TestCase;

class ReceiptDtoTest extends TestCase
{
    public function test_construct_with_app_store_receipt()
    {
        $receipt = new Receipt(
            playerId: 123,
            billingPlatform: 'app_store',
            receipt: 'base64_encoded_receipt_data',
            transactionId: 'txn_123456'
        );

        $this->assertSame(123, $receipt->getPlayerId());
        $this->assertSame('app_store', $receipt->getBillingPlatform());
        $this->assertSame('base64_encoded_receipt_data', $receipt->getReceipt());
        $this->assertNull($receipt->getPurchaseToken());
        $this->assertNull($receipt->getProductId());
        $this->assertSame('txn_123456', $receipt->getTransactionId());
    }

    public function test_construct_with_google_play_token()
    {
        $receipt = new Receipt(
            playerId: 456,
            billingPlatform: 'google_play',
            purchaseToken: 'google_purchase_token_xyz',
            productId: 'com.example.product1',
            transactionId: 'txn_789012'
        );

        $this->assertSame(456, $receipt->getPlayerId());
        $this->assertSame('google_play', $receipt->getBillingPlatform());
        $this->assertNull($receipt->getReceipt());
        $this->assertSame('google_purchase_token_xyz', $receipt->getPurchaseToken());
        $this->assertSame('com.example.product1', $receipt->getProductId());
        $this->assertSame('txn_789012', $receipt->getTransactionId());
    }

    public function test_to_array()
    {
        $receipt = new Receipt(
            playerId: 123,
            billingPlatform: 'app_store',
            receipt: 'base64_encoded_receipt_data',
            transactionId: 'txn_123456'
        );

        $array = $receipt->toArray();

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
        $receipt = new Receipt(
            playerId: 456,
            billingPlatform: 'google_play',
            purchaseToken: 'google_purchase_token_xyz',
            productId: 'com.example.product1'
        );

        $json = $receipt->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame(456, $decoded['player_id']);
        $this->assertSame('google_play', $decoded['billing_platform']);
        $this->assertSame('google_purchase_token_xyz', $decoded['purchase_token']);
        $this->assertSame('com.example.product1', $decoded['product_id']);
    }
}
