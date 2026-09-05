<?php

namespace Nexus\Core\DataTransferObjects;

/**
 * Player
 *
 * プレイヤー情報を表すDTO
 */
class Player
{
    public function __construct(
        private readonly int $id,
        private readonly string $uuid,
        private readonly string $myId,
        private string $name,
        private int $level,
        private int $levelExp,
        private readonly string $createdAt,
        private readonly string $updatedAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getMyId(): string
    {
        return $this->myId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function getLevelExp(): int
    {
        return $this->levelExp;
    }

    public function setLevelExp(int $levelExp): void
    {
        $this->levelExp = $levelExp;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }
}
