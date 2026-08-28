<?php

namespace NexusAlbum\ValueObjects;

use NexusAlbum\Enums\AlbumContentType;

/**
 * AlbumProgress
 *
 * ある種別の収集状況（記録数 / 総数）
 */
final class AlbumProgress
{
    public function __construct(
        private readonly AlbumContentType $contentType,
        private readonly int $unlockedCount,
        private readonly int $totalCount,
    ) {}

    public function getContentType(): AlbumContentType
    {
        return $this->contentType;
    }

    public function getContentTypeValue(): string
    {
        return $this->contentType->value;
    }

    public function getUnlockedCount(): int
    {
        return $this->unlockedCount;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * 収集率（0.0〜1.0）
     *
     * 対象が0件の場合は、割り算を避けて0を返す
     */
    public function calcRate(): float
    {
        if ($this->totalCount <= 0) {
            return 0.0;
        }

        return $this->unlockedCount / $this->totalCount;
    }

    /**
     * 全て集めきったか
     *
     * 対象が0件のときは「集めきった」とは扱わない
     */
    public function isComplete(): bool
    {
        return $this->totalCount > 0 && $this->unlockedCount >= $this->totalCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content_type' => $this->contentType->value,
            'unlocked_count' => $this->unlockedCount,
            'total_count' => $this->totalCount,
            'rate' => round($this->calcRate(), 4),
            'is_complete' => $this->isComplete(),
        ];
    }
}
