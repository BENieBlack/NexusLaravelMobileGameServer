<?php

namespace NexusBilling\DTOs;

use Nexus\Core\Traits\JsonSerializableTrait;

/**
 * レシート検証結果DTO
 * 
 * プラットフォームAPIからのレシート検証結果を保持
 * 
 * @property string $purchaseDate Y-m-d H:i:s 形式の文字列
 */
class VerificationDto
{
    use JsonSerializableTrait;
    public function __construct(
        
        private readonly bool $isValid,                      // 検証が成功したか
        private readonly string $transactionId,              // トランザクションID（ストア固有）
        private readonly string $productId,                  // 商品ID（ストア固有）
        private readonly string $purchaseDate,               // 購入日時 (Y-m-d H:i:s)
        private readonly int $quantity,                      // 購入数量
        private readonly string $originalTransactionId,      // 元のトランザクションID（復元購入等で使用）
        private readonly array $rawResponse,                 // プラットフォームAPIの生レスポンス
        private readonly ?int $priceAmountMicros = null,     // 価格（マイクロ単位、Google Playのみ）
        private readonly ?string $priceCurrencyCode = null,  // 通貨コード（Google Playのみ）
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
            'price_amount_micros' => $this->priceAmountMicros,
            'price_currency_code' => $this->priceCurrencyCode,
        ];
    }

    /**
     * 検証が成功したか取得
     */
    public function getIsValid(): bool
    {
        return $this->isValid;
    }

    /**
     * トランザクションID取得
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * 商品ID取得
     */
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * 購入日時取得
     */
    public function getPurchaseDate(): string
    {
        return $this->purchaseDate;
    }

    /**
     * 購入数量取得
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * 元のトランザクションID取得
     */
    public function getOriginalTransactionId(): string
    {
        return $this->originalTransactionId;
    }

    /**
     * プラットフォームAPIの生レスポンス取得
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    /**
     * 価格（マイクロ単位）取得
     */
    public function getPriceAmountMicros(): ?int
    {
        return $this->priceAmountMicros;
    }

    /**
     * 通貨コード取得
     */
    public function getPriceCurrencyCode(): ?string
    {
        return $this->priceCurrencyCode;
    }
}
