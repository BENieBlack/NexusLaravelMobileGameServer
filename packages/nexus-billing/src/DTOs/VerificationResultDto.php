<?php

namespace LaravelMobileBilling\DTOs;

use NexusUtilities\Traits\JsonSerializableTrait;

/**
 * レシート検証結果DTO
 * 
 * プラットフォームAPIからのレシート検証結果を保持
 * 
 * @property string $purchaseDate Y-m-d H:i:s 形式の文字列
 */
readonly class VerificationResultDto
{
    use JsonSerializableTrait;
    public function __construct(
        public bool $isValid,                      // 検証が成功したか
        public string $transactionId,              // トランザクションID（ストア固有）
        public string $productId,                  // 商品ID（ストア固有）
        public string $purchaseDate,               // 購入日時 (Y-m-d H:i:s)
        public int $quantity,                      // 購入数量
        public string $originalTransactionId,      // 元のトランザクションID（復元購入等で使用）
        public array $rawResponse,                 // プラットフォームAPIの生レスポンス
    ) {}

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'transaction_id' => $this->transactionId,
            'product_id' => $this->productId,
            'purchase_date' => $this->purchaseDate,
            'quantity' => $this->quantity,
            'original_transaction_id' => $this->originalTransactionId,
            'raw_response' => $this->rawResponse,
        ];
    }
}
