<?php

namespace NexusMission\DataTransferObjects;

/**
 * MissionContent
 *
 * ミッションの達成報酬（mst_mission_content の1行）
 */
class MissionContent
{
    public function __construct(
        private readonly string $mstMissionId,
        private readonly string $contentType,
        private readonly string $contentMstId,
        private readonly int $contentQuantity,
        private readonly int $sortOrder,
    ) {}

    public function getMstMissionId(): string
    {
        return $this->mstMissionId;
    }

    /**
     * 報酬の種別（NexusResource\Enums\ResourceType の値）
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContentMstId(): string
    {
        return $this->contentMstId;
    }

    public function getContentQuantity(): int
    {
        return $this->contentQuantity;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content_type' => $this->contentType,
            'content_mst_id' => $this->contentMstId,
            'content_quantity' => $this->contentQuantity,
        ];
    }
}
