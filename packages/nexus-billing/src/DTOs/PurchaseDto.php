<?php

namespace LaravelMobileBilling\DTOs;

use NexusUtilities\Traits\JsonSerializableTrait;

/**
 * 購入情報DTO
 * 
 * 購入処理で使用する情報をまとめて保持
 * 
 * @property string $purchaseDate Y-m-d H:i:s 形式の文字列
 */
readonly class PurchaseDto
{
    use JsonSerializableTrait;
    public function __construct(
        public int $playerId,
        public string $billingPlatform,
        public string $productId,
        public string $transactionId,
        public int $quantity,
        public string $purchaseDate,             // 購入日時 (Y-m-d H:i:s)
        public ?float $price = null,             // 価格（通貨単位）
        public ?string $currency = null,         // 通貨コード（USD, JPY等）
    ) {}

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
            'purchase_date' => $this->purchaseDate,
            'price' => $this->price,
            'currency' => $this->currency,
        ];
    }
}
