<?php

namespace App\Domain\Billing\DTOs;

use Carbon\CarbonImmutable;

/**
 * 購入情報DTO
 * 
 * 購入処理で使用する情報をまとめて保持
 */
readonly class PurchaseInfo
{
    public function __construct(
        public int $playerId,
        public string $billingPlatform,
        public string $productId,
        public string $transactionId,
        public int $quantity,
        public CarbonImmutable $purchaseDate,
        public ?float $price = null,              // 価格（通貨単位）
        public ?string $currency = null,          // 通貨コード（USD, JPY等）
    ) {}

    /**
     * JSON文字列に変換
     */
    public function toJson(): string
    {
        return json_encode([
            'player_id' => $this->playerId,
            'billing_platform' => $this->billingPlatform,
            'product_id' => $this->productId,
            'transaction_id' => $this->transactionId,
            'quantity' => $this->quantity,
            'purchase_date' => $this->purchaseDate->toIso8601String(),
            'price' => $this->price,
            'currency' => $this->currency,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'player_id' => $this->playerId,
            'billing_platform' => $this->billingPlatform,
            'product_id' => $this->productId,
            'transaction_id' => $this->transactionId,
            'quantity' => $this->quantity,
            'purchase_date' => $this->purchaseDate->toIso8601String(),
            'price' => $this->price,
            'currency' => $this->currency,
        ];
    }
}
