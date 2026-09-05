<?php

namespace App\Domain\Gacha\Services;

use App\Domain\InAppPurchase\Services\InAppPurchaseDiamondBalanceService;
use App\Domain\Item\Services\ItemService;
use App\Exceptions\BusinessLogicException;
use App\Models\Mst\MstGachaCost;

/**
 * GachaCostService
 *
 * ガチャコストの消費を行うサービス
 */
class GachaCostService
{
    public function __construct(
        private readonly InAppPurchaseDiamondBalanceService $diamondBalanceService,
        private readonly ItemService $itemService,
    ) {}

    /**
     * ガチャコストを消費
     *
     * @throws BusinessLogicException
     */
    public function consumeCost(int $sysPlayerId, MstGachaCost $cost): void
    {
        $costType = $cost->getAttribute('cost_type');
        $costAmount = $cost->getAttribute('cost_amount');

        switch ($costType) {
            case 'diamond':
                $this->diamondBalanceService->consumeDiamond($sysPlayerId, $costAmount, false);
                break;

            case 'paid_diamond':
                $this->diamondBalanceService->consumeDiamond($sysPlayerId, $costAmount, true);
                break;

            case 'item':
                $costMstId = $cost->getAttribute('cost_mst_id');
                if (! $costMstId) {
                    throw new \Exception('cost_mst_id is required for item cost type');
                }
                $this->itemService->consumeItem($sysPlayerId, $costMstId, $costAmount);
                break;

            default:
                throw new \Exception("Unknown cost type: {$costType}");
        }
    }
}
