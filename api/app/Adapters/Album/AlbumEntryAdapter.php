<?php

namespace App\Adapters\Album;

use App\Models\Trx\TrxAlbum;
use NexusAlbum\DataTransferObjects\AlbumEntry;
use NexusAlbum\Enums\AlbumEntryType;

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
    public static function toDto(TrxAlbum $model): AlbumEntry
    {
        return new AlbumEntry(
            sysPlayerId: $model->getSysPlayerId(),
            type: AlbumEntryType::from($model->getType()),
            masterId: $model->getMasterId(),
            unlockedAt: (string) $model->getUnlockedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxAlbum>  $models
     * @return array<int, AlbumEntry>
     */
    public static function toDtoArray(iterable $models): array
    {
        $dtos = [];

        foreach ($models as $model) {
            $dtos[] = self::toDto($model);
        }

        return $dtos;
    }
}
