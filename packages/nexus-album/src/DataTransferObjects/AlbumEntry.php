<?php

namespace NexusAlbum\DataTransferObjects;

use NexusAlbum\Enums\AlbumEntryType;

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
        private readonly AlbumEntryType $type,
        private readonly string $masterId,
        private readonly string $unlockedAt,
    ) {}

    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    public function getType(): AlbumEntryType
    {
        return $this->type;
    }

    /**
     * 種別の文字列表現（レスポンスやDBに入れる値）
     */
    public function getTypeValue(): string
    {
        return $this->type->value;
    }

    /**
     * マスターID（mst_unit.id など）
     */
    public function getMasterId(): string
    {
        return $this->masterId;
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
    public function isSameTarget(AlbumEntryType $type, string $masterId): bool
    {
        return $this->type === $type && $this->masterId === $masterId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'master_id' => $this->masterId,
            'unlocked_at' => $this->unlockedAt,
        ];
    }
}
