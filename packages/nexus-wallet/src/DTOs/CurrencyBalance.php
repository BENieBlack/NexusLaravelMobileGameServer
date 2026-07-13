<?php

namespace LaravelWallet\DTOs;

use Carbon\CarbonImmutable;

/**
 * 通貨残高情報DTO
 * 
 * 通貨の残高詳細を保持
 */
readonly class CurrencyBalance
{
    public function __construct(
        public int $freeAmount,                    // 無償通貨数
        public int $paidAmount,                    // 有償通貨数
        public int $totalAmount,                   // 合計通貨数
        public ?CarbonImmutable $expireAt = null,  // 有効期限（最短のもの）
    ) {}

    /**
     * JSON文字列に変換
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        return [
            'free_amount' => $this->freeAmount,
            'paid_amount' => $this->paidAmount,
            'total_amount' => $this->totalAmount,
            'expire_at' => $this->expireAt?->toIso8601String(),
        ];
    }
}
