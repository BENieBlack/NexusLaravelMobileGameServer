<?php

namespace App\Http\Responses\Album;

use App\Http\Responses\_BaseResponse;
use NexusAlbum\DataTransferObjects\AlbumEntry;
use NexusAlbum\ValueObjects\AlbumProgress;

/**
 * ListResponse
 *
 * アルバム一覧レスポンス
 *
 * 記録済みの対象と、種別ごとの収集状況を返す
 */
class ListResponse extends _BaseResponse
{
    /**
     * @param  array<int, AlbumEntry>  $albumEntries  記録済みの対象
     * @param  array<int, AlbumProgress>  $albumProgressList  種別ごとの収集状況
     */
    public function __construct(
        private readonly array $albumEntries,
        private readonly array $albumProgressList,
    ) {}

    /**
     * レスポンス配列を取得
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'album_entry_array' => array_map(
                fn (AlbumEntry $albumEntry) => $albumEntry->toArray(),
                $this->albumEntries,
            ),
            'progress_array' => array_map(
                fn (AlbumProgress $albumProgress) => $albumProgress->toArray(),
                $this->albumProgressList,
            ),
        ];
    }
}
