<?php

namespace App\Domain\Album\DataTransferObjects;

use NexusAlbum\DataTransferObjects\AlbumEntry;
use NexusAlbum\ValueObjects\AlbumProgress;

/**
 * ListResult
 *
 * アルバム一覧取得の結果
 */
class ListResult
{
    /**
     * @param  array<int, AlbumEntry>  $albumEntries  記録済みの対象
     * @param  array<int, AlbumProgress>  $albumProgressList  種別ごとの収集状況
     */
    public function __construct(
        public readonly array $albumEntries,
        public readonly array $albumProgressList,
    ) {}
}
