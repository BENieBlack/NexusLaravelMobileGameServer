<?php

namespace LaravelWallet\DTOs;

use NexusUtilities\Traits\JsonSerializableTrait;

/**
 * 通貨残高情報DTO
 * 
 * 通貨の残高詳細を保持
 * 
 * @property string|null $expireAt Y-m-d H:i:s 形式の文字列
 */
readonly class CurrencyBalanceDto
{
    use JsonSerializableTrait;
    public function __construct(
        public int $freeAmount,                    // 無償通貨数
        public int $paidAmount,                    // 有償通貨数
        public int $totalAmount,                   // 合計通貨数
        public ?string $expireAt = null,           // 有効期限（最短のもの） (Y-m-d H:i:s)
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
            'expire_at' => $this->expireAt,
        ];
    }
}
