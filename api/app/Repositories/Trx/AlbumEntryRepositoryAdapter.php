<?php

namespace App\Repositories\Trx;

use App\Adapters\Album\AlbumEntryAdapter;
use NexusAlbum\DataTransferObjects\AlbumEntry;
use NexusAlbum\Enums\AlbumEntryType;
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
    public function exists(int $sysPlayerId, AlbumEntryType $type, string $masterId): bool
    {
        return $this->trxAlbumRepository->selectByTypeAndMasterId($type->value, $masterId) !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function insert(AlbumEntry $albumEntry): void
    {
        $this->trxAlbumRepository->insertEntry(
            $albumEntry->getSysPlayerId(),
            $albumEntry->getTypeValue(),
            $albumEntry->getMasterId(),
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
            $type = $trxAlbum->getType();
            $countByType[$type] = ($countByType[$type] ?? 0) + 1;
        }

        return $countByType;
    }
}
