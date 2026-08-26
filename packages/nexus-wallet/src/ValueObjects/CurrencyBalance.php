<?php

namespace NexusWallet\ValueObjects;

use Nexus\Core\Traits\JsonSerializableTrait;

/**
 * 通貨残高 Value Object
 *
 * 通貨の残高（無償／有償）を保持する不変オブジェクト。
 *
 * - 合計値は free + paid から導出するため、内訳と合計がずれることはない
 * - 残高は負にならない（生成時に検証する）
 * - 同一性ではなく値で等価判定する
 *
 * @property string|null $expireAt Y-m-d H:i:s 形式の文字列
 */
final class CurrencyBalance
{
    use JsonSerializableTrait;

    /**
     * @param  int  $freeAmount  無償通貨数
     * @param  int  $paidAmount  有償通貨数
     * @param  string|null  $expireAt  有効期限（最短のもの） (Y-m-d H:i:s)
     *
     * @throws \InvalidArgumentException 残高が負の場合
     */
    public function __construct(
        private readonly int $freeAmount,
        private readonly int $paidAmount,
        private readonly ?string $expireAt = null,
    ) {
        if ($freeAmount < 0) {
            throw new \InvalidArgumentException("無償通貨数は0以上である必要があります: {$freeAmount}");
        }

        if ($paidAmount < 0) {
            throw new \InvalidArgumentException("有償通貨数は0以上である必要があります: {$paidAmount}");
        }
    }

    /**
     * 残高ゼロのインスタンスを生成
     */
    public static function zero(?string $expireAt = null): self
    {
        return new self(0, 0, $expireAt);
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
     * 有効期限取得
     */
    public function getExpireAt(): ?string
    {
        return $this->expireAt;
    }

    /**
     * 残高が空か
     */
    public function isEmpty(): bool
    {
        return $this->getTotalAmount() === 0;
    }

    /**
     * 指定数量を支払えるか（無償・有償の合計で判定）
     */
    public function canAfford(int $amount): bool
    {
        return $this->getTotalAmount() >= $amount;
    }

    /**
     * 加算した新しい残高を返す
     */
    public function add(int $freeAmount = 0, int $paidAmount = 0): self
    {
        return new self(
            $this->freeAmount + $freeAmount,
            $this->paidAmount + $paidAmount,
            $this->expireAt,
        );
    }

    /**
     * 有償優先で消費した新しい残高を返す
     *
     * @throws \InvalidArgumentException 残高が不足している場合
     */
    public function subtractPaidFirst(int $amount): self
    {
        if (! $this->canAfford($amount)) {
            throw new \InvalidArgumentException(
                "残高が不足しています。必要: {$amount}, 保有: {$this->getTotalAmount()}"
            );
        }

        $paidConsumed = min($this->paidAmount, $amount);
        $freeConsumed = $amount - $paidConsumed;

        return new self(
            $this->freeAmount - $freeConsumed,
            $this->paidAmount - $paidConsumed,
            $this->expireAt,
        );
    }

    /**
     * 値が等しいか
     */
    public function equals(self $other): bool
    {
        return $this->freeAmount === $other->freeAmount
            && $this->paidAmount === $other->paidAmount
            && $this->expireAt === $other->expireAt;
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
            'expire_at' => $this->expireAt,
        ];
    }
}
