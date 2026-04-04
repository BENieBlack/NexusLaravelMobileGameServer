<?php

namespace App\Domain\Gacha\Services;

use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Domain\Delivery\Services\DeliveryService;

/**
 * GachaPrizeService
 *
 * ガチャ景品の付与を行うサービス
 * DeliveryServiceを使用して統一的に景品を配送
 */
class GachaPrizeService
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
    ) {
    }

    /**
     * 景品リストを付与
     *
     * @param int $sysPlayerId
     * @param array $prizes
     * @return void
     */
    public function grantPrizes(int $sysPlayerId, array $prizes): void
    {
        $deliveryContents = [];

        foreach ($prizes as $prize) {
            $deliveryContents[] = $this->createDeliveryContent(
                $prize['content_type'],
                $prize['content_id'],
                $prize['amount']
            );
        }

        // DeliveryService経由で配送（新しいパターン: addContents + deliver）
        $this->deliveryService->addContents($deliveryContents);
        $this->deliveryService->deliver($sysPlayerId);
    }

    /**
     * 景品データからDeliveryContentを作成
     *
     * @param string $contentType
     * @param string $contentId
     * @param int $amount
     * @return DeliveryContent
     */
    private function createDeliveryContent(string $contentType, string $contentId, int $amount): DeliveryContent
    {
        return match ($contentType) {
            'item' => DeliveryContent::item($contentId, $amount),
            'unit' => DeliveryContent::unit($contentId, $amount),
            'equipment' => DeliveryContent::equipment($contentId, $amount),
            default => throw new \Exception("Unsupported content type: {$contentType}"),
        };
    }
}
