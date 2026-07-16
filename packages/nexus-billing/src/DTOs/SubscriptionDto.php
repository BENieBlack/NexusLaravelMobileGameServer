<?php

namespace LaravelMobileBilling\DTOs;

use NexusUtilities\Traits\JsonSerializableTrait;

/**
 * サブスクリプション状態DTO
 * 
 * サブスクリプション商品の現在の状態を保持
 * 
 * @property string $expiresAt Y-m-d H:i:s 形式の文字列
 * @property string|null $cancelledAt Y-m-d H:i:s 形式の文字列
 */
class SubscriptionDto
{
    use JsonSerializableTrait;
    public function __construct(
        
        private readonly bool $isActive,                   // サブスクリプションが有効か
        private readonly string $expiresAt,                // 有効期限 (Y-m-d H:i:s)
        public bool $autoRenew,                  // 自動更新が有効か
        public ?string $state = null,            // 状態（active, expired, cancelled等）
        public ?string $cancelledAt = null,      // キャンセル日時 (Y-m-d H:i:s)
    ) {}

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'is_active' => $this->isActive,
            'expires_at' => $this->expiresAt,
            'auto_renew' => $this->autoRenew,
            'state' => $this->state,
            'cancelled_at' => $this->cancelledAt,
        ];
    }
}
