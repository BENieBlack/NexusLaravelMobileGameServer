<?php

namespace NexusTag\DataTransferObjects;

/**
 * TagContent
 *
 * タグとマスターの紐付け（mst_tag_content の1行）
 */
class TagContent
{
    public function __construct(
        private readonly string $mstTagId,
        private readonly string $contentType,
        private readonly string $contentMstId,
    ) {}

    public function getMstTagId(): string
    {
        return $this->mstTagId;
    }

    /**
     * タグを付ける対象の種別（unit / equipment / item）
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * 種別に対応するマスターのID（mst_unit.id など）
     */
    public function getContentMstId(): string
    {
        return $this->contentMstId;
    }

    /**
     * 指定した対象への紐付けか
     */
    public function isFor(string $contentType, string $contentMstId): bool
    {
        return $this->contentType === $contentType && $this->contentMstId === $contentMstId;
    }
}
