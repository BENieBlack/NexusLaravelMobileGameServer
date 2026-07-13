<?php

namespace LaravelMobileBilling\DTOs;

use Carbon\CarbonImmutable;

/**
 * レシート検証結果DTO
 * 
 * プラットフォームAPIからのレシート検証結果を保持
 */
readonly class VerificationResult
{
    public function __construct(
        public bool $isValid,                      // 検証が成功したか
        public string $transactionId,              // トランザクションID（ストア固有）
        public string $productId,                  // 商品ID（ストア固有）
        public CarbonImmutable $purchaseDate,      // 購入日時
        public int $quantity,                      // 購入数量
        public string $originalTransactionId,      // 元のトランザクションID（復元購入等で使用）
        public array $rawResponse,                 // プラットフォームAPIの生レスポンス
    ) {}

    /**
     * JSON文字列に変換
     */
    public function toJson(): string
    {
        return json_encode([
            'is_valid' => $this->isValid,
            'transaction_id' => $this->transactionId,
            'product_id' => $this->productId,
            'purchase_date' => $this->purchaseDate->toIso8601String(),
            'quantity' => $this->quantity,
            'original_transaction_id' => $this->originalTransactionId,
            'raw_response' => $this->rawResponse,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'transaction_id' => $this->transactionId,
            'product_id' => $this->productId,
            'purchase_date' => $this->purchaseDate->toIso8601String(),
            'quantity' => $this->quantity,
            'original_transaction_id' => $this->originalTransactionId,
            'raw_response' => $this->rawResponse,
        ];
    }
}
