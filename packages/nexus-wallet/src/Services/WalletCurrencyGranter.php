<?php

namespace NexusWallet\Services;

use Nexus\Core\ResourceDelivery\Contracts\CurrencyGranterInterface;

/**
 * WalletCurrencyGranter
 *
 * nexus-core の CurrencyGranterInterface を、WalletService で実装する。
 * 報酬の配送（通貨・ポイント・自然資源）が、残高と有効期限を持つ Wallet に入る。
 */
class WalletCurrencyGranter implements CurrencyGranterInterface
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function grantCurrency(
        int $sysPlayerId,
        string $currencyId,
        int $freeAmount,
        int $paidAmount,
        ?string $expireAt,
    ): void {
        $this->walletService->addCurrency($sysPlayerId, $currencyId, $freeAmount, $paidAmount, $expireAt);
    }
}
