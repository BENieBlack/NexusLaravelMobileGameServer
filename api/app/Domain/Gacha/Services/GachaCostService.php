<?php

namespace App\Domain\Gacha\Services;

use App\Domain\InAppPurchase\Services\DiamondService;
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
        private readonly DiamondService $diamondService,
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
                $this->diamondService->consumeDiamond($sysPlayerId, $costAmount, false);
                break;

            case 'paid_diamond':
                $this->diamondService->consumeDiamond($sysPlayerId, $costAmount, true);
                break;

            case 'item':
                $costId = $cost->getAttribute('cost_id');
                if (! $costId) {
                    throw new \Exception('cost_id is required for item cost type');
                }
                $this->itemService->consumeItem($sysPlayerId, $costId, $costAmount);
                break;

            default:
                throw new \Exception("Unknown cost type: {$costType}");
        }
    }
}
