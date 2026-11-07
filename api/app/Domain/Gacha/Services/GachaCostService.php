<?php

namespace App\Domain\Gacha\Services;

use App\Domain\InAppPurchase\Contracts\DiamondBalanceInterface;
use App\Domain\Item\Contracts\ItemInventoryInterface;
use App\Exceptions\BusinessLogicException;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Mst\MstGachaCost;

/**
 * GachaCostService
 *
 * ガチャコストの消費を行うサービス
 *
 * 足りないときは消費する前に業務エラー（DIAMOND_NOT_ENOUGH / ITEM_NOT_ENOUGH）にする。
 * 消費側に任せると汎用の例外になり、クライアントには500で返ってしまう
 */
class GachaCostService
{
    public function __construct(
        private readonly DiamondBalanceInterface $diamondBalanceService,
        private readonly ItemInventoryInterface $itemInventory,
    ) {}

    /**
     * ガチャコストを消費
     *
     * @throws BusinessLogicException 足りない場合
     * @throws GameException マスターのコスト設定が不正な場合
     */
    public function consumeCost(int $sysPlayerId, MstGachaCost $mstGachaCost): void
    {
        $costType = $mstGachaCost->getAttribute('cost_type');
        $costAmount = (int) $mstGachaCost->getAttribute('cost_amount');

        match ($costType) {
            'diamond' => $this->consumeDiamond($sysPlayerId, $costAmount, isPaidOnly: false),
            'paid_diamond' => $this->consumeDiamond($sysPlayerId, $costAmount, isPaidOnly: true),
            'item' => $this->consumeItem($sysPlayerId, $mstGachaCost->getAttribute('cost_mst_id'), $costAmount),
            default => throw new GameException(
                GameErrorCode::GACHA_COST_NOT_FOUND,
                "Unknown cost type: {$costType} (mst_gacha_cost.id={$mstGachaCost->getAttribute('id')})"
            ),
        };
    }

    private function consumeDiamond(int $sysPlayerId, int $amount, bool $isPaidOnly): void
    {
        $owned = $this->diamondBalanceService->findDiamondAmount($sysPlayerId, $isPaidOnly);

        if ($owned < $amount) {
            throw BusinessLogicException::diamondNotEnough($amount, $owned);
        }

        $this->diamondBalanceService->consumeDiamond($sysPlayerId, $amount, $isPaidOnly);
    }

    private function consumeItem(int $sysPlayerId, ?string $mstItemId, int $amount): void
    {
        if ($mstItemId === null || $mstItemId === '') {
            throw new GameException(GameErrorCode::GACHA_COST_NOT_FOUND, 'cost_mst_id is required for item cost type');
        }

        $owned = $this->itemInventory->findItemAmount($sysPlayerId, $mstItemId);

        if ($owned < $amount) {
            throw BusinessLogicException::itemNotEnough($mstItemId, $amount, $owned);
        }

        $this->itemInventory->consumeItem($sysPlayerId, $mstItemId, $amount);
    }
}
