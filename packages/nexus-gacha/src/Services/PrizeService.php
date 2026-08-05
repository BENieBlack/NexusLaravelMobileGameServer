<?php

namespace NexusGacha\Services;

use NexusGacha\Dto\GachaPrizeDto;
use NexusResource\DTOs\ResourceDto;
use NexusResourceDelivery\Services\ResourceDeliveryService;

/**
 * PrizeService
 * 
 * ガチャ景品の付与を行うサービス
 */
class PrizeService
{
    public function __construct(
        private readonly ResourceDeliveryService $resourceDeliveryService,
    ) {
    }

    /**
     * 景品リストを付与
     *
     * @param int $sysPlayerId
     * @param array<GachaPrizeDto> $prizes
     * @return void
     */
    public function grantPrizes(int $sysPlayerId, array $prizes): void
    {
        $resources = [];

        foreach ($prizes as $prize) {
            $resources[] = $this->createResource(
                $prize->getContentType(),
                $prize->getContentId(),
                $prize->getAmount()
            );
        }

        // ResourceDeliveryService経由で配送
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
            'item' => ResourceDto::item($contentId, $amount),
            'unit' => ResourceDto::unit($contentId, $amount),
            'equipment' => ResourceDto::equipment($contentId, $amount),
            default => throw new \Exception("Unsupported content type: {$contentType}"),
        };
    }
}
