<?php

namespace NexusGuild\DataTransferObjects;

/**
 * Guild
 *
 * ギルド情報のデータ転送オブジェクト
 */
class Guild
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $description,
        private readonly int $level,
        private readonly int $exp,
        private readonly int $maxMembers,
        private readonly int $currentMembers,
        private readonly string $createdAt,
        private readonly string $updatedAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getExp(): int
    {
        return $this->exp;
    }

    public function getMaxMembers(): int
    {
        return $this->maxMembers;
    }

    public function getCurrentMembers(): int
    {
        return $this->currentMembers;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    /**
     * 配列に変換
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'level' => $this->level,
            'exp' => $this->exp,
            'max_members' => $this->maxMembers,
            'current_members' => $this->currentMembers,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
