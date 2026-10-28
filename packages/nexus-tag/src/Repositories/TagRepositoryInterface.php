<?php

namespace NexusTag\Repositories;

use NexusTag\DataTransferObjects\TagContent;

/**
 * TagRepositoryInterface
 *
 * タグの紐付けマスター（mst_tag_content）へのアクセスを抽象化する
 */
interface TagRepositoryInterface
{
    /**
     * タグとマスターの紐付けを全件取得する
     *
     * @return array<int, TagContent>
     */
    public function selectAllContents(): array;
}
