<?php

namespace NexusAlbum\Repositories;

use NexusAlbum\DataTransferObjects\AlbumEntry;
use NexusAlbum\Enums\AlbumEntryType;

/**
 * AlbumEntryRepositoryInterface
 *
 * アルバムの記録へのアクセスを抽象化する
 */
interface AlbumEntryRepositoryInterface
{
    /**
     * プレイヤーの記録を全件取得する
     *
     * @return array<int, AlbumEntry>
     */
    public function selectByPlayerId(int $sysPlayerId): array;

    /**
     * 指定した対象が記録済みかどうか
     */
    public function exists(int $sysPlayerId, AlbumEntryType $type, string $masterId): bool;

    /**
     * 記録を1件追加する
     *
     * 既に記録済みの対象が渡ることは無い前提（AlbumServiceが事前に判定する）
     */
    public function insert(AlbumEntry $albumEntry): void;

    /**
     * 種別ごとの記録数を取得する
     *
     * @return array<string, int> 種別の値 => 記録数
     */
    public function countByType(int $sysPlayerId): array;
}
