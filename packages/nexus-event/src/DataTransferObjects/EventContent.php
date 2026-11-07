<?php

namespace NexusEvent\DataTransferObjects;

/**
 * EventContent
 *
 * イベントに束ねられたコンテンツ（mst_event_content の1行）
 */
class EventContent
{
    /**
     * @param  string  $contentType  EventContentType の値
     * @param  string  $contentMstId  コンテンツのマスターID（例: mst_gacha.id）
     */
    public function __construct(
        private readonly string $mstEventId,
        private readonly string $contentType,
        private readonly string $contentMstId,
        private readonly int $sortOrder,
    ) {}

    public function getMstEventId(): string
    {
        return $this->mstEventId;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContentMstId(): string
    {
        return $this->contentMstId;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
