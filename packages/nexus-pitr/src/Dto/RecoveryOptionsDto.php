<?php

namespace NexusPitr\Dto;

class RecoveryOptionsDto
{
    public function __construct(
        private readonly string $shard,
        private readonly string $snapshotTime,
        private readonly string $targetTime,
        private readonly ?int $playerId = null,
        private readonly ?string $tableName = null,
        private readonly bool $dryRun = false,
        private readonly bool $verify = false,
        private readonly int $batchSize = 100
    ) {}

    public function getShard(): string
    {
        return $this->shard;
    }

    public function getSnapshotTime(): string
    {
        return $this->snapshotTime;
    }

    public function getTargetTime(): string
    {
        return $this->targetTime;
    }

    public function getPlayerId(): ?int
    {
        return $this->playerId;
    }

    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    public function getDryRun(): bool
    {
        return $this->dryRun;
    }

    public function getVerify(): bool
    {
        return $this->verify;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }
}
