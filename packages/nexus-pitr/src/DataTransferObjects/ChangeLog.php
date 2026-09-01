<?php

namespace NexusPitr\DataTransferObjects;

use DateTime;

class ChangeLog
{
    /**
     * @param  array<string, mixed>|null  $beforeData  変更前の行データ（INSERTならnull）
     * @param  array<string, mixed>|null  $afterData  変更後の行データ（DELETEならnull）
     * @param  array<string, mixed>  $primaryKey  対象行を特定する主キーの値
     * @param  array<int, mixed>|null  $stackTrace  記録時のスタックトレース
     */
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

    /**
     * @return array<string, mixed>|null
     */
    public function getBeforeData(): ?array
    {
        return $this->beforeData;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAfterData(): ?array
    {
        return $this->afterData;
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<int, mixed>|null
     */
    public function getStackTrace(): ?array
    {
        return $this->stackTrace;
    }
}
