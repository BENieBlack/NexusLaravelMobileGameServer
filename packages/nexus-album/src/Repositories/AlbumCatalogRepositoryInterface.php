<?php

namespace NexusAlbum\Repositories;

use NexusAlbum\Enums\AlbumContentType;

/**
 * AlbumCatalogRepositoryInterface
 *
 * 「アルバムに載る対象が全部で何件あるか」をマスターから答える
 *
 * 何をアルバムの対象にするかはゲーム側の都合（マスターの is_album_target）で
 * 決まるため、総数の求め方はアプリケーション層に委ねる。
 */
interface AlbumCatalogRepositoryInterface
{
    /**
     * 種別ごとのアルバム対象の総数を取得する
     *
     * @return array<string, int> 種別の値 => 総数
     */
    public function countTargetsByType(): array;

    /**
     * 指定した対象がアルバムに載るものかどうか
     *
     * 対象外のマスターを渡された場合に記録しないための判定に使う
     */
    public function isTarget(AlbumContentType $contentType, string $contentMstId): bool;
}
