<?php

namespace App\Domain\Trade\Services;

use App\Domain\InAppPurchase\Contracts\DiamondBalanceInterface;
use App\Domain\Item\Contracts\ItemInventoryInterface;
use App\Domain\Trade\Constants\TradeCostType;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use NexusTrade\Contracts\TradeCostPayerInterface;

/**
 * TradeCostPayerAdapter
 *
 * 交換所のコストを支払う（NexusTrade の TradeCostPayerInterface の実装）
 *
 * ダイヤは InAppPurchase、アイテムとコインなどの通貨は Item の公開APIで消費する
 * （Item は mst_item.is_wallet を見て trx_item と Wallet に振り分ける）。
 * 足りないときは消費する前に業務エラーにする
 */
class TradeCostPayerAdapter implements TradeCostPayerInterface
{
    public function __construct(
        private readonly DiamondBalanceInterface $diamondBalance,
        private readonly ItemInventoryInterface $itemInventory,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @throws GameException 足りない、またはマスターの設定が不正な場合
     */
    public function pay(int $sysPlayerId, string $costType, ?string $costMstId, int $amount): void
    {
        match ($costType) {
            TradeCostType::DIAMOND => $this->payDiamond($sysPlayerId, $amount, isPaidOnly: false),
            TradeCostType::PAID_DIAMOND => $this->payDiamond($sysPlayerId, $amount, isPaidOnly: true),
            TradeCostType::ITEM, TradeCostType::WALLET => $this->payItem($sysPlayerId, $costMstId, $amount, $costType),
            default => throw new GameException(GameErrorCode::TRADE_COST_INVALID, "Unknown cost type: {$costType}"),
        };
    }

    private function payDiamond(int $sysPlayerId, int $amount, bool $isPaidOnly): void
    {
        $owned = $this->diamondBalance->findDiamondAmount($sysPlayerId, $isPaidOnly);

        if ($owned < $amount) {
            throw new GameException(
                GameErrorCode::DIAMOND_NOT_ENOUGH,
                "Diamond is not enough. required={$amount}, owned={$owned}"
            );
        }

        $this->diamondBalance->consumeDiamond($sysPlayerId, $amount, $isPaidOnly);
    }

    private function payItem(int $sysPlayerId, ?string $mstItemId, int $amount, string $costType): void
    {
        if ($mstItemId === null || $mstItemId === '') {
            throw new GameException(GameErrorCode::TRADE_COST_INVALID, "cost_mst_id is required for {$costType} cost");
        }

        $owned = $this->itemInventory->findItemAmount($sysPlayerId, $mstItemId);

        if ($owned < $amount) {
            throw new GameException(
                GameErrorCode::ITEM_NOT_ENOUGH,
                "Item is not enough. mst_item_id={$mstItemId}, required={$amount}, owned={$owned}"
            );
        }

        $this->itemInventory->consumeItem($sysPlayerId, $mstItemId, $amount);
    }
}
