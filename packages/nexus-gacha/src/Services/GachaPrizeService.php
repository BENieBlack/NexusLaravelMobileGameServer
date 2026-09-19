<?php

namespace NexusGacha\Services;

use NexusGacha\ValueObjects\GachaPrize;
use NexusResource\DataTransferObjects\Resource;
use NexusResourceDelivery\Services\ResourceDeliveryService;

/**
 * GachaPrizeService
 *
 * ガチャ景品の付与を行うサービス
 */
class GachaPrizeService
{
    public function __construct(
        private readonly ResourceDeliveryService $resourceDeliveryService,
    ) {}

    /**
     * 景品リストを付与
     *
     * @param  int  $sysPlayerId
     * @param  array<GachaPrize>  $prizes
     * @return void
     */
    public function grantPrizes(int $sysPlayerId, array $prizes): void
    {
        $resources = [];

        foreach ($prizes as $prize) {
            $resources[] = $this->createResource(
                $prize->getContentType(),
                $prize->getContentMstId(),
                $prize->getAmount(),
                $prize->getContentOption(),
            );
        }

        // ResourceDeliveryService経由で配送
        $this->resourceDeliveryService->addResources($resources);
        $this->resourceDeliveryService->deliver($sysPlayerId);
    }

    /**
     * 景品データからResourceを作成
     *
     * @param  string  $contentType
     * @param  string  $contentMstId
     * @param  int  $amount
     * @param  array<string, mixed>|null  $contentOption
     * @return resource
     */
    private function createResource(
        string $contentType,
        string $contentMstId,
        int $amount,
        ?array $contentOption,
    ): Resource
    {
        $resource = match ($contentType) {
            'item' => Resource::item($contentMstId, $amount),
            'unit' => Resource::unit(
                $contentMstId,
                $amount,
                $contentOption['grade'] ?? null,
                $contentOption['level'] ?? null,
            ),
            'equipment' => Resource::equipment($contentMstId, $amount),
            default => throw new \Exception("Unsupported content type: {$contentType}"),
        };

        if ($contentType === 'equipment' && $contentOption !== null) {
            $resource->setMetadata($contentOption);
        }

        return $resource;
    }
}
