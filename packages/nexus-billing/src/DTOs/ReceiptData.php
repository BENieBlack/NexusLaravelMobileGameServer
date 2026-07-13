<?php

namespace LaravelMobileBilling\DTOs;

use NexusUtilities\Traits\JsonSerializableTrait;

/**
 * レシートデータDTO
 * 
 * クライアントから送信されるレシート情報を保持
 */
readonly class ReceiptData
{
    use JsonSerializableTrait;
    public function __construct(
        public int $playerId,
        public string $billingPlatform,
        public ?string $receipt = null,           // AppStore用: base64エンコードされたレシート
        public ?string $purchaseToken = null,     // GooglePlay用: 購入トークン
        public ?string $productId = null,         // GooglePlay用: 商品ID
        public ?string $transactionId = null,     // トランザクションID（オプション）
    ) {}

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'player_id' => $this->playerId,
            'billing_platform' => $this->billingPlatform,
            'receipt' => $this->receipt,
            'purchase_token' => $this->purchaseToken,
            'product_id' => $this->productId,
            'transaction_id' => $this->transactionId,
        ];
    }
}
