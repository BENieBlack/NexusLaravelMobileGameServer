<?php

namespace NexusPitr\DataTransferObjects;

use DateTime;

class ChangeLog
{
    public function __construct(
        private readonly string $uniqueRequestId,
        private readonly int $sysPlayerId,
        private readonly string $shardConnection,
        private readonly string $tableName,
        private readonly string $operation,
        private readonly ?array $beforeData,
        private readonly ?array $afterData,
        private readonly array $primaryKey,
        private readonly DateTime $systemAt,
        private readonly ?string $apiEndpoint = null,
        private readonly ?array $stackTrace = null
    ) {}

    public function getUniqueRequestId(): string
    {
        return $this->uniqueRequestId;
    }

    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    public function getShardConnection(): string
    {
        return $this->shardConnection;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getBeforeData(): ?array
    {
        return $this->beforeData;
    }

    public function getAfterData(): ?array
    {
        return $this->afterData;
    }

    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
    }

    public function getSystemAt(): DateTime
    {
        return $this->systemAt;
    }

    public function resolveApiEndpoint(): ?string
    {
        return $this->apiEndpoint;
    }

    public function getStackTrace(): ?array
    {
        return $this->stackTrace;
    }
}
