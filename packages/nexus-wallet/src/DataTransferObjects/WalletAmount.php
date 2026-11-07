<?php

namespace NexusWallet\DataTransferObjects;

/**
 * WalletAmount
 *
 * 通貨の現在値（trx_wallet の1行）を表すDTO。期限切れの分を引く前の値。
 */
class WalletAmount
{
    public function __construct(
        private readonly int $freeAmount,
        private readonly int $paidAmount,
    ) {}

    public function getFreeAmount(): int
    {
        return $this->freeAmount;
    }

    public function getPaidAmount(): int
    {
        return $this->paidAmount;
    }

    public function getTotalAmount(): int
    {
        return $this->freeAmount + $this->paidAmount;
    }
}
