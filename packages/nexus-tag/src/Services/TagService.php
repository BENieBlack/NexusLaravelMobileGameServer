<?php

namespace NexusTag\Services;

use NexusTag\DataTransferObjects\TagContent;
use NexusTag\Repositories\TagRepositoryInterface;

/**
 * TagService
 *
 * マスターに付いたタグを引く
 *
 * タグはミッションの条件、図鑑の絞り込み、ガチャやイベントの特効など、
 * 機能をまたいで使い回す。どの機能も「この対象はどのタグを持つか」
 * 「このタグを持つ対象はどれか」の2つを問うので、その2方向だけを提供する。
 */
class TagService
{
    public function __construct(
        private readonly TagRepositoryInterface $tagRepository,
    ) {}

    /**
     * 対象に付いているタグIDの一覧を取得する
     *
     * @param  string  $contentType  対象の種別（unit / equipment / item）
     * @param  string  $contentMstId  種別に対応するマスターのID
     * @return array<int, string>
     */
    public function findTagIds(string $contentType, string $contentMstId): array
    {
        $tagIds = [];

        foreach ($this->tagRepository->selectAllContents() as $tagContent) {
            if ($tagContent->isFor($contentType, $contentMstId)) {
                $tagIds[] = $tagContent->getMstTagId();
            }
        }

        return $tagIds;
    }

    /**
     * 対象が指定したタグを持つか
     */
    public function hasTag(string $contentType, string $contentMstId, string $mstTagId): bool
    {
        return in_array($mstTagId, $this->findTagIds($contentType, $contentMstId), true);
    }

    /**
     * 指定したタグを持つ対象のマスターIDの一覧を取得する
     *
     * @param  string  $mstTagId  タグID
     * @param  string  $contentType  対象の種別（unit / equipment / item）
     * @return array<int, string>
     */
    public function findContentMstIds(string $mstTagId, string $contentType): array
    {
        $contentMstIds = array_map(
            fn (TagContent $tagContent) => $tagContent->getContentMstId(),
            array_filter(
                $this->tagRepository->selectAllContents(),
                fn (TagContent $tagContent) => $tagContent->getMstTagId() === $mstTagId
                    && $tagContent->getContentType() === $contentType,
            ),
        );

        return array_values($contentMstIds);
    }
}
