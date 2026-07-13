<?php

namespace LaravelWallet\DTOs;

/**
 * 通貨操作結果DTO
 * 
 * 通貨の加算・消費結果を保持
 */
readonly class CurrencyOperationResult
{
    public function __construct(
        public int $freeAmount,      // 操作した無償通貨数
        public int $paidAmount,      // 操作した有償通貨数
        public int $totalAmount,     // 操作した合計通貨数
        public int $currentBalance,  // 操作後の残高
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
            'current_balance' => $this->currentBalance,
        ];
    }
}
