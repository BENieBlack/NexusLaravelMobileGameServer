<?php

namespace NexusWallet\Tests\Unit\Services;

use Nexus\Core\ResourceDelivery\Contracts\CurrencyGranterInterface;
use NexusWallet\Services\WalletCurrencyGranter;
use NexusWallet\Services\WalletService;
use NexusWallet\ValueObjects\CurrencyOperationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * WalletCurrencyGranter のユニットテスト
 *
 * nexus-core の配送ハンドラーが呼ぶ契約を、WalletService::addCurrency に渡すだけの薄い実装。
 * 無償・有償の数量と有効期限を、そのまま引き渡すことを固定する。
 */
class WalletCurrencyGranterTest extends TestCase
{
    #[Test]
    public function 配送の契約として使える(): void
    {
        $granter = new WalletCurrencyGranter($this->createMock(WalletService::class));

        $this->assertInstanceOf(CurrencyGranterInterface::class, $granter);
    }

    #[Test]
    public function 無償と有償の数量と有効期限を_wallet_serviceに引き渡す(): void
    {
        $walletService = $this->createMock(WalletService::class);
        $walletService->expects($this->once())
            ->method('addCurrency')
            ->with(777, 'event_coin', 30, 20, '2026-12-31 23:59:59')
            ->willReturn(new CurrencyOperationResult(30, 20, 50));

        (new WalletCurrencyGranter($walletService))->grantCurrency(777, 'event_coin', 30, 20, '2026-12-31 23:59:59');
    }

    #[Test]
    public function 有効期限が無ければ無期限として引き渡す(): void
    {
        $walletService = $this->createMock(WalletService::class);
        $walletService->expects($this->once())
            ->method('addCurrency')
            ->with(777, 'gold', 100, 0, null)
            ->willReturn(new CurrencyOperationResult(100, 0, 100));

        (new WalletCurrencyGranter($walletService))->grantCurrency(777, 'gold', 100, 0, null);
    }
}
