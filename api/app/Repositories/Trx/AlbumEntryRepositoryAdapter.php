<?php

namespace App\Repositories\Trx;

use App\Adapters\Album\AlbumEntryAdapter;
use NexusAlbum\DataTransferObjects\AlbumEntry;
use NexusAlbum\Enums\AlbumContentType;
use NexusAlbum\Repositories\AlbumEntryRepositoryInterface;

/**
 * AlbumEntryRepositoryAdapter
 *
 * TrxAlbumRepository（Model）と AlbumEntryRepositoryInterface（DTO）の橋渡し
 */
class AlbumEntryRepositoryAdapter implements AlbumEntryRepositoryInterface
{
    public function __construct(
        private readonly TrxAlbumRepository $trxAlbumRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerId(int $sysPlayerId): array
    {
        return AlbumEntryAdapter::toDtoArray($this->trxAlbumRepository->selectAllByPlayer());
    }

    /**
     * {@inheritDoc}
     */
    public function exists(int $sysPlayerId, AlbumContentType $contentType, string $contentMstId): bool
    {
        return $this->trxAlbumRepository->selectByContentTypeAndMstId($contentType->value, $contentMstId) !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function insert(AlbumEntry $albumEntry): void
    {
        $this->trxAlbumRepository->insertEntry(
            $albumEntry->getSysPlayerId(),
            $albumEntry->getContentTypeValue(),
            $albumEntry->getContentMstId(),
            $albumEntry->getUnlockedAt(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function countByType(int $sysPlayerId): array
    {
        $countByType = [];

        foreach ($this->trxAlbumRepository->selectAllByPlayer() as $trxAlbum) {
            $contentType = $trxAlbum->getContentType();
            $countByType[$contentType] = ($countByType[$contentType] ?? 0) + 1;
        }

        return $countByType;
    }
}
