<?php

namespace LaravelMobileBilling\DTOs;

use Carbon\CarbonImmutable;
use NexusUtilities\Traits\JsonSerializableTrait;

/**
 * サブスクリプション状態DTO
 * 
 * サブスクリプション商品の現在の状態を保持
 */
readonly class SubscriptionStatus
{
    use JsonSerializableTrait;
    public function __construct(
        public bool $isActive,                   // サブスクリプションが有効か
        public CarbonImmutable $expiresAt,       // 有効期限
        public bool $autoRenew,                  // 自動更新が有効か
        public ?string $state = null,            // 状態（active, expired, cancelled等）
        public ?CarbonImmutable $cancelledAt = null,  // キャンセル日時
    ) {}

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'is_active' => $this->isActive,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'auto_renew' => $this->autoRenew,
            'state' => $this->state,
            'cancelled_at' => $this->cancelledAt?->toIso8601String(),
        ];
    }
}
