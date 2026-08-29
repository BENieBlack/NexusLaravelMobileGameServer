<?php

namespace App\Domain\Album\Handlers;

use NexusAlbum\Enums\AlbumContentType;
use NexusAlbum\Services\AlbumService;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Handlers\ResourceDeliveryHandlerInterface;

/**
 * AlbumRecordingDeliveryHandler
 *
 * 本来のHandlerに配布を任せたうえで、アルバムへの記録を追加する
 *
 * ResourceDeliveryService::findHandler() は最初に一致したHandlerだけを返すため、
 * アルバム用のHandlerを並べて登録すると本来の配布を乗っ取ってしまう。
 * そこで本来のHandlerを包んで、配布のあとに記録する形にしている。
 *
 * 記録するかどうか（アルバム対象か、既に記録済みか）は AlbumService が判断する。
 */
class AlbumRecordingDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    /**
     * 配布のリソース種別と、アルバムの記録種別の対応
     */
    private const TYPE_MAP = [
        'unit' => AlbumContentType::UNIT,
        'equipment' => AlbumContentType::EQUIPMENT,
        'item' => AlbumContentType::ITEM,
    ];

    public function __construct(
        private readonly ResourceDeliveryHandlerInterface $innerHandler,
        private readonly AlbumService $albumService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $resourceDeliveryContent): void
    {
        // 配布そのものは本来のHandlerに任せる
        $this->innerHandler->handle($sysPlayerId, $resourceDeliveryContent);

        $albumEntryType = self::TYPE_MAP[$resourceDeliveryContent->getTypeValue()] ?? null;

        if ($albumEntryType === null) {
            return;
        }

        $this->albumService->unlock(
            $sysPlayerId,
            $albumEntryType,
            $resourceDeliveryContent->getId(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function supports(ResourceType|string $type): bool
    {
        return $this->innerHandler->supports($type);
    }
}
