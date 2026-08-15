<?php

namespace NexusPlayer\DataTransferObjects;

/**
 * PlayerDevice
 *
 * プレイヤーデバイス情報を表すDTO
 */
class PlayerDevice
{
    public function __construct(
        private readonly int $id,
        private readonly int $sysPlayerId,
        private readonly string $uuid,
        private readonly ?array $deviceInfo,
        private readonly string $lastLoginAt,
        private readonly string $createdAt,
        private readonly string $updatedAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getDeviceInfo(): ?array
    {
        return $this->deviceInfo;
    }

    public function getLastLoginAt(): string
    {
        return $this->lastLoginAt;
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
