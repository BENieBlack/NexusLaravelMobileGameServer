<?php

namespace NexusWallet\DataTransferObjects;

/**
 * WalletBalanceEntry
 *
 * 通貨の残高明細（trx_wallet_balance の1行）を表すDTO。FIFO消費の単位。
 */
class WalletBalanceEntry
{
    /**
     * @param  string|null  $expireAt  有効期限 (Y-m-d H:i:s)。NULLは無期限
     */
    public function __construct(
        private readonly int $id,
        private readonly bool $isPaid,
        private readonly int $currentAmount,
        private readonly int $initialAmount,
        private readonly ?string $expireAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getIsPaid(): bool
    {
        return $this->isPaid;
    }

    public function getCurrentAmount(): int
    {
        return $this->currentAmount;
    }

    public function getInitialAmount(): int
    {
        return $this->initialAmount;
    }

    public function getExpireAt(): ?string
    {
        return $this->expireAt;
    }
}
