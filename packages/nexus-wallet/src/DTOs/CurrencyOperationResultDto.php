<?php

namespace LaravelWallet\DTOs;

use NexusUtilities\Traits\JsonSerializableTrait;

/**
 * 通貨操作結果DTO
 * 
 * 通貨の加算・消費結果を保持
 */
class CurrencyOperationResultDto
{
    use JsonSerializableTrait;
    public function __construct(
        
        private readonly int $freeAmount,      // 操作した無償通貨数
        private readonly int $paidAmount,      // 操作した有償通貨数
        private readonly int $totalAmount,     // 操作した合計通貨数
        private readonly int $currentBalance,  // 操作後の残高
    ) {}

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

    /**
     * 無償通貨数取得
     */
    public function getFreeAmount(): int
    {
        return $this->freeAmount;
    }

    /**
     * 有償通貨数取得
     */
    public function getPaidAmount(): int
    {
        return $this->paidAmount;
    }

    /**
     * 合計通貨数取得
     */
    public function getTotalAmount(): int
    {
        return $this->totalAmount;
    }

    /**
     * 現在残高取得
     */
    public function getCurrentBalance(): int
    {
        return $this->currentBalance;
    }
}
