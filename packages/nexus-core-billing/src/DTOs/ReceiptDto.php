<?php

namespace NexusBilling\DTOs;

use Nexus\Core\Traits\JsonSerializableTrait;

/**
 * レシートデータDTO
 * 
 * クライアントから送信されるレシート情報を保持
 */
class ReceiptDto
{
    use JsonSerializableTrait;
    public function __construct(
        
        private readonly int $playerId,
        private readonly string $billingPlatform,
        private readonly ?string $receipt = null,           // AppStore用: base64エンコードされたレシート
        private readonly ?string $purchaseToken = null,     // GooglePlay用: 購入トークン
        private readonly ?string $productId = null,         // GooglePlay用: 商品ID
        private readonly ?string $transactionId = null,     // トランザクションID（オプション）
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

    /**
     * プレイヤーID取得
     */
    public function getPlayerId(): int
    {
        return $this->playerId;
    }

    /**
     * 決済プラットフォーム取得
     */
    public function getBillingPlatform(): string
    {
        return $this->billingPlatform;
    }

    /**
     * レシート取得（AppStore用）
     */
    public function getReceipt(): ?string
    {
        return $this->receipt;
    }

    /**
     * 購入トークン取得（GooglePlay用）
     */
    public function getPurchaseToken(): ?string
    {
        return $this->purchaseToken;
    }

    /**
     * 商品ID取得
     */
    public function getProductId(): ?string
    {
        return $this->productId;
    }

    /**
     * トランザクションID取得
     */
    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }
}
