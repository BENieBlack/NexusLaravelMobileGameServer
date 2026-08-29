<?php

namespace NexusAlbum\DataTransferObjects;

use NexusAlbum\Enums\AlbumContentType;

/**
 * AlbumEntry
 *
 * アルバムに記録された1件（Repository ↔ Service の受け渡し用）
 *
 * 「何を」「いつ初めて手に入れた／解放したか」だけを持つ。
 * 数量や現在の所持状況は持たない（手放しても記録は残る）。
 */
class AlbumEntry
{
    public function __construct(
        private readonly int $sysPlayerId,
        private readonly AlbumContentType $contentType,
        private readonly string $contentMstId,
        private readonly string $unlockedAt,
    ) {}

    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    public function getContentType(): AlbumContentType
    {
        return $this->contentType;
    }

    /**
     * 種別の文字列表現（レスポンスやDBに入れる値）
     */
    public function getContentTypeValue(): string
    {
        return $this->contentType->value;
    }

    /**
     * マスターID（mst_unit.id など）
     */
    public function getContentMstId(): string
    {
        return $this->contentMstId;
    }

    /**
     * 解放日時（Y-m-d H:i:s形式）
     */
    public function getUnlockedAt(): string
    {
        return $this->unlockedAt;
    }

    /**
     * 同じ対象を指しているか
     */
    public function isSameTarget(AlbumContentType $contentType, string $contentMstId): bool
    {
        return $this->contentType === $contentType && $this->contentMstId === $contentMstId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content_type' => $this->contentType->value,
            'content_mst_id' => $this->contentMstId,
            'unlocked_at' => $this->unlockedAt,
        ];
    }
}
