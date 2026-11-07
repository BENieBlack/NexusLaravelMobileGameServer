<?php

namespace App\Adapters\Album;

use App\Models\Trx\TrxAlbum;
use NexusAlbum\DataTransferObjects\AlbumEntry;
use NexusAlbum\Enums\AlbumContentType;

/**
 * AlbumEntryAdapter
 *
 * TrxAlbum Model と AlbumEntry の変換を行うアダプター
 */
class AlbumEntryAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(TrxAlbum $trxAlbum): AlbumEntry
    {
        return new AlbumEntry(
            sysPlayerId: $trxAlbum->getSysPlayerId(),
            contentType: AlbumContentType::from($trxAlbum->getContentType()),
            contentMstId: $trxAlbum->getContentMstId(),
            unlockedAt: (string) $trxAlbum->getUnlockedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxAlbum>  $trxAlbums
     * @return array<int, AlbumEntry>
     */
    public static function toDtoArray(iterable $trxAlbums): array
    {
        $dtos = [];

        foreach ($trxAlbums as $trxAlbum) {
            $dtos[] = self::toDto($trxAlbum);
        }

        return $dtos;
    }
}
