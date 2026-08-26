<?php

namespace NexusWallet\ValueObjects;

use Nexus\Core\Traits\JsonSerializableTrait;

/**
 * 通貨操作結果 Value Object
 *
 * 通貨の加算・消費で「実際に動いた量」と「操作後の残高」を保持する不変オブジェクト。
 * 合計値は無償・有償の内訳から導出する。
 */
final class CurrencyOperationResult
{
    use JsonSerializableTrait;

    /**
     * @param  int  $freeAmount  操作した無償通貨数
     * @param  int  $paidAmount  操作した有償通貨数
     * @param  int  $currentBalance  操作後の残高
     *
     * @throws \InvalidArgumentException 値が負の場合
     */
    public function __construct(
        private readonly int $freeAmount,
        private readonly int $paidAmount,
        private readonly int $currentBalance,
    ) {
        if ($freeAmount < 0) {
            throw new \InvalidArgumentException("操作した無償通貨数は0以上である必要があります: {$freeAmount}");
        }

        if ($paidAmount < 0) {
            throw new \InvalidArgumentException("操作した有償通貨数は0以上である必要があります: {$paidAmount}");
        }

        if ($currentBalance < 0) {
            throw new \InvalidArgumentException("操作後の残高は0以上である必要があります: {$currentBalance}");
        }
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
     * 合計通貨数取得（無償 + 有償）
     */
    public function getTotalAmount(): int
    {
        return $this->freeAmount + $this->paidAmount;
    }

    /**
     * 現在残高取得
     */
    public function getCurrentBalance(): int
    {
        return $this->currentBalance;
    }

    /**
     * 実際に通貨が動いたか
     */
    public function hasChanged(): bool
    {
        return $this->getTotalAmount() > 0;
    }

    /**
     * 値が等しいか
     */
    public function equals(self $other): bool
    {
        return $this->freeAmount === $other->freeAmount
            && $this->paidAmount === $other->paidAmount
            && $this->currentBalance === $other->currentBalance;
    }

    /**
     * 配列に変換
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'free_amount' => $this->freeAmount,
            'paid_amount' => $this->paidAmount,
            'total_amount' => $this->getTotalAmount(),
            'current_balance' => $this->currentBalance,
        ];
    }
}
