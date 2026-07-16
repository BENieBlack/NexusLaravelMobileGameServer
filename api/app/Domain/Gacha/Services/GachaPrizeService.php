<?php

namespace App\Domain\Gacha\Services;

use NexusResource\DTOs\ResourceDto;
use NexusResourceDelivery\Services\ResourceDeliveryService;

/**
 * GachaPrizeService
 *
 * ガチャ景品の付与を行うサービス
 * ResourceDeliveryServiceを使用して統一的に景品を配送
 */
class GachaPrizeService
{
    public function __construct(
        private readonly ResourceDeliveryService $resourceDeliveryService,
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
        $resources = [];

        foreach ($prizes as $prize) {
            $resources[] = $this->createResource(
                $prize['content_type'],
                $prize['content_id'],
                $prize['amount']
            );
        }

        // ResourceDeliveryService経由で配送（新しいパターン: addResources + deliver）
        $this->resourceDeliveryService->addResources($resources);
        $this->resourceDeliveryService->deliver($sysPlayerId);
    }

    /**
     * 景品データからResourceを作成
     *
     * @param string $contentType
     * @param string $contentId
     * @param int $amount
     * @return ResourceDto
     */
    private function createResource(string $contentType, string $contentId, int $amount): ResourceDto
    {
        return match ($contentType) {
            'item' => Resource::item($contentId, $amount),
            'unit' => Resource::unit($contentId, $amount),
            'equipment' => Resource::equipment($contentId, $amount),
            default => throw new \Exception("Unsupported content type: {$contentType}"),
        };
    }
}
